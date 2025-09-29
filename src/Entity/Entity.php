<?php

namespace Amtgard\ActiveRecordOrm\Entity;

use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;

class Entity
{
    use Builder;

    private ResultSet $resultSet;
    private TableSchema $schema;

    private array $fields;
    private array $changes = [];

    public function __get(string $name) {
        return $this->fields[$name] ?? null;
    }

    public function __set(string $name, $value) {
        $this->schema->hasField($name);

        if ($value !== $this->fields[$name]) {
            $this->changes[$name] = $value;
            $this->fields[$name] = $value;
        }
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

    public function flush(EntityMapper $mapper) {
        if ($this->isDirty()) {
            $table = $mapper->getTable();
            $table->clear();
            $primaryKey = $this->getPrimaryKey()->getName();
            $table->$primaryKey = $this->getPrimaryKey()->getValue();
            foreach ($this->changes as $field => $value) {
                $table->$field = $value;
            }
            $table->save();
        }
    }

    #[PostInit]
    private function postInit() {
        $this->fields = $this->resultSet->getFieldMap();
    }
}