<?php

namespace Amtgard\ActiveRecordOrm\Interface\Entity;

use Amtgard\ActiveRecordOrm\Interface\Table\FieldType;
use PDOException;
use PDOStatement;
use Amtgard\ActiveRecordOrm\Interface\Table\FieldDefinition;

class RecordSet
{
    private PDOStatement $__statement;
    private array $__fields;

    private bool $__hasActiveRecord;

    public function __construct(PDOStatement $statement) {
        $this->__statement = $statement;
        $this->__hasActiveRecord = false;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getDefinition(): array {
        $definition = [];
        for ($c = 0; $c < $this->__statement->columnCount(); $c++) {
            $def = $this->__statement->getColumnMeta($c);
            $name = $def['name'];
            $definition[] = new FieldDefinition($def, $def['name'], $this->$name);
        }
        return $definition;
    }

    public function next(): void {
        try {
            $this->__fields = $this->__statement->fetch();
            $this->__hasActiveRecord = $this->__fields === false ? false : true;
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    }

    public function size(): int {
        return $this->__statement->rowCount();
    }

    public function hasActiveRecord(): bool {
        return $this->__hasActiveRecord;
    }

    public function __get(string  $field): mixed {
        return $this->__fields[$field] ?? null;
    }
}