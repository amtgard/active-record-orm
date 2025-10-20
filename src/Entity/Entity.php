<?php

namespace Amtgard\ActiveRecordOrm\Entity;

use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use DateTime;
use Optional\Optional;

class Entity implements EntityInterface
{
    use Builder;

    private ?ResultSet $resultSet = null;
    private TableSchema $schema;

    private array $fields;
    private array $changes = [];

    protected EntityMapper $mapper;

    public function __get(string $name) {
        return $this->fields[$name] ?? null;
    }

    public function __set(string $name, $value) {
        $this->schema->hasField($name);

        if ($value !== $this->fields[$name]) {
            $convertedValue = $this->valueToFieldType($name, $value);
            $this->changes[$name] = $convertedValue;
            $this->fields[$name] = $convertedValue;
        }
    }

    public function getSchema(): TableSchema {
        return $this->schema;
    }

    public function convertTo($targetClass): EntityInterface {
        return $targetClass::mapEntity($this);
    }

    private function valueToFieldType(string $fieldName, $value) {
        $field = $this->schema->getField($fieldName);
        $type = $field->getType();
        switch (gettype($value)) {
            case "object":
                if ($value instanceof DateTime && $type == FieldType::DATETIME) {
                    return $value->format('Y-m-d H:i:s');
                }
                if ($value instanceof DateTime && $type == FieldType::INTEGER) {
                    return $value->format('U');
                }
                if ($value instanceof Entity && $type == FieldType::INTEGER) {
                    return $value->getPrimaryKey()->getValue();
                }
                break;
        }
        return $value;
    }

    public function isDirty(): bool {
        return !empty($this->changes);
    }

    public function getChanges(): array {
        return $this->changes;
    }

    public function getPrimaryKey(): FieldDefinition {
        $pkName = $this->schema->getPrimaryKey()->getName();
        return FieldDefinition::builder()
            ->value($this->fields[$pkName])
            ->name($pkName)
            ->build();
    }

    public function persist(EntityMapper $mapper) {
        if ($this->isDirty()) {
            $table = $mapper->getTable();
            $table->clear();
            $primaryKey = $this->getPrimaryKey()->getName();
            if (!is_null($this->getPrimaryKey()->getValue())) {
                $table->$primaryKey = $this->getPrimaryKey()->getValue();
            }
            foreach ($this->changes as $field => $value) {
                $table->$field = $value;
            }
            $table->save();
        }
    }

    public function getMapper(): EntityMapper {
        return $this->mapper;
    }

    #[PostInit]
    private function postInit() {
        $this->fields = Optional::ofNullable($this->resultSet)
            ->map(function($resultSet) {
                return $resultSet->getFieldMap();
            })
            ->orElseGet(function() {
                $fields = [];
                foreach($this->schema->getFields() as $field) {
                    $fields[$field->getName()] = $this->changes[$field->getName()];
                }
                return $fields;
            });
    }
}