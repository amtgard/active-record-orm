<?php

namespace Amtgard\ActiveRecordOrm\Trait;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\OnSet;
use Amtgard\Traits\Builder\PostInit;
use Amtgard\Traits\Builder\PreInit;
use DateTime;

trait RepositoryEntityTrait
{
    private EntityMapper $mapper;
    private EntityInterface $entity;
    private array $entityMapInfo = [];
    private string $entityMapperEntityId;
    private string $repositoryClass;

    public function isDirty(): bool
    {
        return $this->entity->isDirty();
    }

    public function getChanges(): array
    {
        return $this->entity->getChanges();
    }

    public function getPrimaryKey(): FieldDefinition
    {
        return $this->entity->getPrimaryKey();
    }

    public function persist(EntityMapper $mapper)
    {
        $this->entity->persist($mapper);
        $this->mapInternalEntityToFields();
    }

    public function getSchema(): TableSchema {
        return $this->entity->schema;
    }

    public function getInternalEntity(): EntityInterface {
        return $this->entity;
    }

    public function setInternalEntity(EntityInterface $entity) {
        $this->entity = $entity;
    }

    public static function toRepositoryEntity(?EntityInterface $entity): ?EntityInterface {
        if (is_null($entity)) {
            return null;
        }

        $parentClass = static::class;

        $instanceBuilder = $parentClass::builder();
        $entity = in_array(RepositoryEntityTrait::class, class_uses($entity)) ? $entity->entity : $entity;
        $instanceBuilder->entity($entity);
        $instance = $instanceBuilder->build();

        $schema = $entity->getSchema();
        foreach ($instance->getEntityMapInfo() as $instanceField => $mapInfo) {
            $sourceField = $mapInfo['source'];
            static::fieldTypeConversions($instance, $instanceField, $schema, $mapInfo, $entity, $sourceField);
        }
        return $instance;
    }

    private static function fieldTypeConversions(RepositoryEntity &$instance, $instanceField, TableSchema $sourceSchema, $mapInfo, EntityInterface $entity, $sourceField) {
        $sourceFieldValue = $entity->$sourceField;
        switch ($mapInfo['destinationType']) {
            case 'DateTime': {
                switch ($sourceSchema->getFields()[$sourceField]->getType()) {
                    case FieldType::DATETIME: $instance->$instanceField = new DateTime($sourceFieldValue); return;
                    case FieldType::INTEGER: $instance->$instanceField = DateTime::createFromFormat('U', $sourceFieldValue); return;
                }
                $instance->$instanceField = new DateTime();
                return;
            }
            default: {
                $interfaces = class_implements($mapInfo['destinationType']);
                if ($interfaces && count($interfaces) > 0) {
                    if (in_array(EntityInterface::class, $interfaces)) {
                        // Resurrect alternate entity from repo
                        if (isset($mapInfo['backingReferencePk'])) {
                            $backingReferencePk = $mapInfo['backingReferencePk'];
                            $instance->$backingReferencePk = $sourceFieldValue;
                            return;
                        }
                    }
                }
            }
        }
        $instance->$instanceField = $sourceFieldValue;
    }

    private function getEntityMapperAttributeValue(): ?EntityMapper
    {
        $reflection = new \ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(EntityOf::class);
        if (count($attributes) === 0) {
            return null;
        }

        $attribute = $attributes[0];
        $arguments = $attribute->getArguments();
        $this->repositoryClass = $arguments[0] ?? null;

        if (!is_string($this->repositoryClass) || $this->repositoryClass === '') {
            throw new \RuntimeException(sprintf(
                'EntityOf attribute on %s must declare a repository class name.',
                static::class
            ));
        }

        EntityManager::getManager()->registerRepository($this->repositoryClass);

        if (!is_subclass_of($this->repositoryClass, EntityRepositoryInterface::class)) {
            throw new \RuntimeException(sprintf(
                'EntityOf repository %s must implement %s.',
                $this->repositoryClass,
                EntityRepositoryInterface::class
            ));
        }

        $entityManager = EntityManager::getManager();
        if (!$entityManager instanceof EntityManager) {
            throw new \RuntimeException('EntityManager must be configured before using EntityTrait.');
        }

        $mapperName = $this->repositoryClass::getTableName();
        $mapper = $entityManager->getMapper($mapperName);

        if (!$mapper instanceof EntityMapper) {
            throw new \RuntimeException(sprintf(
                'EntityManager does not have an EntityMapper registered for table "%s".',
                $mapperName
            ));
        }

        $this->mapper = $mapper;

        return $mapper;
    }

    #[PreInit]
    private function preInit() {
        if (!isset($this->mapper)) {
            $this->getEntityMapperAttributeValue();
        }
    }

    private function buildEmptyInternalEntity() {
        $repositoryClass = $this->repositoryClass;
        $repository = EntityManager::getManager()->getRepository($repositoryClass);
        $this->entity = $repository->createMapperEntity();
    }

    private function mapFieldsToInternalEntity() {
        $schema = $this->entity->getSchema();
        foreach ($this->getEntityMapInfo() as $instanceField => $mapInfo) {
            $sourceField = $mapInfo['source'];
            if (!isset($this->$instanceField)) {
                continue;
            }
            switch ($mapInfo['destinationType']) {
                case 'DateTime': {
                    switch ($schema->getFields()[$sourceField]->getType()) {
                        case FieldType::DATETIME: $this->entity->$sourceField = $this->$instanceField->format('Y-m-d H:i:s'); break;
                        case FieldType::INTEGER: $this->entity->$sourceField = $this->$instanceField->getTimestamp(); break;
                    }
                }
                default: {
                    $interfaces = class_implements($mapInfo['destinationType']);
                    if ($interfaces && count($interfaces) > 0) {
                        if (in_array(EntityInterface::class, $interfaces)) {
                            $this->entity->$sourceField = $this->$instanceField->id;
                        }
                    }
                }
            }
        }
    }

    private function mapInternalEntityToFields() {
        $schema = $this->entity->getSchema();
        foreach ($this->getEntityMapInfo() as $instanceField => $mapInfo) {
            $sourceField = $mapInfo['source'];
            static::fieldTypeConversions($this, $instanceField, $schema, $mapInfo, $this->entity, $sourceField);
        }
    }

    #[PostInit]
    private function postInit() {
        $this->entityMapperEntityId = md5(microtime());
        $this->entityMapInfo = static::buildEntityMapInfo();

        if (!isset($this->entity)) {
            $this->buildEmptyInternalEntity();
            $this->mapFieldsToInternalEntity();
        }
    }

    public static function buildEntityMapInfo() {
        $map = [];
        $thisClassReflection = new \ReflectionClass(static::class);
        foreach ($thisClassReflection->getProperties(\ReflectionProperty::IS_PRIVATE) as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if (in_array($attribute->getName(), [ Field::class, PrimaryKey::class ])) {
                    $args = $attribute->getArguments();
                    $entityFieldName = count($args) > 0 ? $args[0] : $property->getName();
                    $map[$property->getName()] = [
                        'annotation' => $attribute->getName(),
                        'source' => $entityFieldName,
                        'destinationType' => $property->getType()->getName(),
                        'backingReferencePk' => count($args) > 1 ? $args[1] : null,
                    ];
                }
            }
        }
        return $map;
    }

    #[OnSet]
    private function onSetField($name, $value) {
        $entityFieldName = $this->getEntityMapInfo()[$name]['source'];
        $this->entity->$entityFieldName = $value;
        return $value;
    }
}