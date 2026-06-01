<?php

namespace Amtgard\ActiveRecordOrm\Entity\Repository;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\EntityReference;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Factory\EntityFactory;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\OnSet;
use Amtgard\Traits\Builder\PostInit;
use Amtgard\Traits\Builder\PreInit;
use Amtgard\Traits\Builder\ToBuilder;
use DateTime;
use Optional\Optional;

abstract class RepositoryEntity implements EntityInterface
{
    use Builder, ToBuilder, Data;

    protected EntityMapper $mapper;
    protected EntityInterface $entity;
    protected EntityFieldMap $entityFieldMap;
    protected string $entityMapperEntityId;
    protected string $repositoryClass;

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

    public function persist(EntityMapper $mapper): EntityInterface
    {
        $this->mapFieldsToInternalEntity();
        $this->entity->persist($mapper);
        $this->mapInternalEntityToFields();
        return $this->entity;
    }

    public function getSchema(): TableSchema {
        return $this->entity->getSchema();
    }

    public function getInternalEntity(): EntityInterface {
        $this->mapFieldsToInternalEntity();
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
        $entity = ($entity instanceof RepositoryEntity) ? $entity->entity : $entity;
        $instanceBuilder->entity($entity);
        $instance = $instanceBuilder->build();

        $schema = $entity->getSchema();
        foreach ($instance->getEntityFieldMap()->getInstanceFields() as $instanceField) {
            $mapInfo = $instance->getEntityFieldMap()->getField($instanceField);
            $sourceField = $mapInfo->getSource();
            static::fieldTypeConversions($instance, $instanceField, $schema, $mapInfo, $entity, $sourceField);
        }
        return $instance;
    }

    protected function getEntityManager(): EntityManager {
        return EntityManager::getManager();
    }

    private static function fieldTypeConversions(RepositoryEntity &$instance, $instanceField, TableSchema $sourceSchema, $mapInfo, EntityInterface $entity, $sourceField) {
        $sourceFieldValue = $entity->$sourceField;
        if ($mapInfo->getDestinationType() == \DateTimeInterface::class) {
            if (is_null($sourceFieldValue)) {
                $instance->$instanceField = null;
                return;
            }
            switch ($sourceSchema->getFields()[$sourceField]->getType()) {
                case FieldType::DATETIME: $instance->$instanceField = new DateTime($sourceFieldValue); return;
                case FieldType::INTEGER: $instance->$instanceField = DateTime::createFromFormat('U', $sourceFieldValue); return;
            }
            $instance->$instanceField = new DateTime();
            return;
        } else if (RepositoryEntity::isEntityInterfaceClass($mapInfo)) {
            $backingReferencePk = $mapInfo->getBackingReferencePk();
            $instance->$backingReferencePk = $sourceFieldValue;
            return;
        } else if (self::isEnumType($mapInfo)) {
            $enumType = $mapInfo->getDestinationType();
            $instance->$instanceField = !is_null($sourceFieldValue) ? ($enumType::tryFrom($sourceFieldValue) ?? null) : null;
            return;
        }
        if (Optional::ofNullable($sourceFieldValue)->isPresent() || $mapInfo->getNullable()) {
            $instance->$instanceField = $sourceFieldValue;
        }
    }

    private function extractBackingFieldValue($value) {
        if (RepositoryEntity::valueIsEntityInterface($value)) {
            return $value->getId();
        }
        return $value;
    }

    private static function isEnumType($mapInfo) {
        $type = $mapInfo->getDestinationType();
        if ($type && (class_exists($type) || interface_exists($type))) {
            $interfaces = class_implements($type);
            if ($interfaces && count($interfaces) > 0 && in_array(\BackedEnum::class, $interfaces)) {
                return true;
            }
        }
        return false;
    }

    private static function isEntityInterfaceClass($mapInfo) {
        $type = $mapInfo->getDestinationType();
        if ($type && (class_exists($type) || interface_exists($type))) {
            $interfaces = class_implements($type);
            if ($interfaces && count($interfaces) > 0 && in_array(EntityInterface::class, $interfaces) && !is_null($mapInfo->getBackingReferencePk())) {
                return true;
            }
        }
        return false;
    }

    private static function valueIsEntityInterface($value) {
        if (!is_object($value)) {
            return false;
        }
        $interfaces = class_implements($value);
        if ($interfaces && count($interfaces) > 0 && in_array(EntityInterface::class, $interfaces)) {
            return true;
        }
        return false;
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

        $this->getEntityManager()->registerRepository($this->repositoryClass);

        if (!is_subclass_of($this->repositoryClass, EntityRepositoryInterface::class)) {
            throw new \RuntimeException(sprintf(
                'EntityOf repository %s must implement %s.',
                $this->repositoryClass,
                EntityRepositoryInterface::class
            ));
        }

        $mapperName = $this->repositoryClass::getTableName();
        $mapper = $this->getEntityManager()->getMapper($mapperName);

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
    protected function preInit() {
        $this->entityMapperEntityId = md5(microtime());
        $this->entityFieldMap = static::buildEntityFieldMap();

        if (!isset($this->mapper)) {
            $this->getEntityMapperAttributeValue();
        }

        if (!isset($this->entity)) {
            $this->buildEmptyInternalEntity();
        }
    }

    private function buildEmptyInternalEntity() {
        $this->entity = EntityFactory::build($this->mapper);
    }

    private function mapFieldsToInternalEntity() {
        $schema = $this->entity->getSchema();
        foreach ($this->getEntityFieldMap()->getInstanceFields() as $instanceField) {
            $mapInfo = $this->getEntityFieldMap()->getField($instanceField);
            $sourceField = $mapInfo->getSource();
            if (!isset($this->$instanceField)) {
                continue;
            }
            if ($mapInfo->getDestinationType() == \DateTimeInterface::class) {
                switch ($schema->getFields()[$sourceField]->getType()) {
                    case FieldType::DATETIME: $this->entity->$sourceField = $this->$instanceField->format('Y-m-d H:i:s'); break;
                    case FieldType::INTEGER: $this->entity->$sourceField = $this->$instanceField->getTimestamp(); break;
                }
            } else {
                $type = $mapInfo->getDestinationType();
                $interfaces = ($type && (class_exists($type) || interface_exists($type))) ? class_implements($type) : [];
                if ($interfaces && count($interfaces) > 0) {
                    if (in_array(EntityInterface::class, $interfaces)) {
                        $this->entity->$sourceField = $this->$instanceField->id;
                    }
                } else {
                    $this->entity->$sourceField = $this->$instanceField;
                }
            }

        }
    }

    private function mapInternalEntityToFields() {
        $schema = $this->entity->getSchema();
        foreach ($this->getEntityFieldMap()->getInstanceFields() as $instanceField) {
            $mapInfo = $this->getEntityFieldMap()->getField($instanceField);
            $sourceField = $mapInfo->getSource();
            static::fieldTypeConversions($this, $instanceField, $schema, $mapInfo, $this->entity, $sourceField);
        }
    }

    #[PostInit]
    protected function postInit() {
        $this->mapFieldsToInternalEntity();
    }

    public static function buildEntityFieldMap() {
        $map = EntityFieldMap::builder()->build();
        $thisClassReflection = new \ReflectionClass(static::class);
        foreach ($thisClassReflection->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED) as $property) {
            $mappedInfo = static::extractAttributeData($property);
            if (Optional::ofNullable($mappedInfo)->isPresent()) {
                $map->setField($property->getName(), $mappedInfo);
            }
        }
        return $map;
    }

    private static function extractAttributeData(\ReflectionProperty $property): ?MappedInfo
    {
        $attributes = $property->getAttributes();
        if (count(array_intersect(array_map(fn($attribute) => $attribute->getName(), $attributes), [Field::class, FieldType::class, PrimaryKey::class, EntityReference::class])) == 0)
            return null;

        $mappedInfoBuilder = MappedInfo::builder();
        $backingReferencePk = null;
        $fieldTypeFromFieldAttribute = null;
        $fieldTypeFromFieldTypeAttribute = null;
        $entityFieldName = null;
        $annotation = null;

        // First pass: collect all attribute data
        foreach ($attributes as $attribute) {
            switch ($attribute->getName()) {
                case EntityReference::class:
                    $args = $attribute->getArguments();
                    $backingReferencePk = count($args) > 0 ? $args[0] : null;
                    break;
                case Field::class:
                case PrimaryKey::class:
                    $args = $attribute->getArguments();
                    $entityFieldName = count($args) > 0 ? $args[0] : $property->getName();
                    $annotation = $attribute->getName();
                    // Collect type from Field's second parameter (preferred)
                    if (count($args) > 1) {
                        $fieldTypeFromFieldAttribute = $args[1];
                    }
                    break;
                case FieldType::class:
                    // Collect type from FieldType attribute (backward compatibility)
                    $args = $attribute->getArguments();
                    if (count($args) > 0) {
                        $fieldTypeFromFieldTypeAttribute = $args[0];
                    }
                    break;
            }
        }

        // Set annotation and source
        if ($annotation) {
            $mappedInfoBuilder->annotation($annotation);
            $mappedInfoBuilder->source($entityFieldName ?? $property->getName());
        } else {
            return null;
        }

        // Set backing reference property
        $mappedInfoBuilder->backingReferencePk($backingReferencePk);

        // Determine destination type: prefer Field's second parameter, then FieldType attribute, then property type
        $propertyType = $property->getType();
        if ($fieldTypeFromFieldAttribute) {
            $mappedInfoBuilder->destinationType($fieldTypeFromFieldAttribute);
            $mappedInfoBuilder->nullable(Optional::ofNullable($propertyType)
                ->map(fn($type) => $type->allowsNull())
                ->orElse(true));
        } elseif ($fieldTypeFromFieldTypeAttribute) {
            $mappedInfoBuilder->destinationType(Optional::ofNullable($propertyType)
                ->map(fn($type) => $type->getName())
                ->orElse($fieldTypeFromFieldTypeAttribute));
            $mappedInfoBuilder->nullable(Optional::ofNullable($propertyType)
                ->map(fn($type) => $type->allowsNull())
                ->orElse(true));
        } else {
            $mappedInfoBuilder->destinationType($propertyType ? $propertyType->getName() : null);
            $mappedInfoBuilder->nullable($propertyType ? $propertyType->allowsNull() : true);
        }

        return $mappedInfoBuilder->build();
    }

    public function getEntityFieldMap(): EntityFieldMap {
        return $this->entityFieldMap;
    }

    #[OnSet]
    protected function onSetField($name, $value) {
        $entityFieldName = Optional::ofNullable($this->getEntityFieldMap()->getField($name))
            ->map(fn($mapInfo) => $mapInfo->getSource())
            ->orElse(null);
        if (Optional::ofNullable($entityFieldName)->isPresent()) {
            $this->entity->$entityFieldName = $this->extractBackingFieldValue($value);
        }
        return $value;
    }

}