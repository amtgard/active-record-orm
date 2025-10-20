<?php

namespace Amtgard\ActiveRecordOrm\Trait;

use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\OnSet;
use Amtgard\Traits\Builder\PostInit;
use DateTime;

trait EntityTrait
{
    private EntityInterface $entity;
    private array $changes = [];
    private array $__entityMapInfo = [];
    private string $__entityMapperEntityId;

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

    public function flush(EntityMapper $mapper)
    {
        $this->entity->flush($mapper);
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

    public static function mapEntity(EntityInterface $entity): EntityInterface {
        $parentClass = static::class;

        $instanceBuilder = $parentClass::builder();
        $entity = in_array(EntityTrait::class, class_uses($entity)) ? $entity->entity : $entity;
        $instanceBuilder->entity($entity);
        $instance = $instanceBuilder->build();

        $schema = $entity->getSchema();
        foreach ($instance->get__entityMapInfo() as $instanceField => $mapInfo) {
            $sourceField = $mapInfo['source'];
            static::fieldTypeConversions($instance, $instanceField, $schema, $mapInfo, $entity, $sourceField);
        }
        return $instance;
    }

    private static function fieldTypeConversions(EntityInterface &$instance, $instanceField, TableSchema $sourceSchema, $mapInfo, $entity, $sourceField) {
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

    #[PostInit]
    private function postInit() {
        $this->__entityMapperEntityId = md5(microtime());
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
        $this->__entityMapInfo = $map;
    }

    #[OnSet]
    private function onSetField($name, $value) {
        $entityFieldName = $this->get__entityMapInfo()[$name]['source'];
        $this->entity->$entityFieldName = $value;
        return $value;
    }
}