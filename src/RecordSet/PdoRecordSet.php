<?php

namespace Amtgard\ActiveRecordOrm\RecordSet;

use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use PDOException;
use PDOStatement;

class PdoRecordSet implements RecordSet
{
    private ?PDOStatement $__statement;
    private ?array $__fieldDefinition = null;
    private ?array $__pdoDefinition = null;
    private array|bool $__fields = false;
    private bool $__hasActiveRecord;

    public function __construct(PDOStatement $statement) {
        $this->__statement = $statement;
        $this->__hasActiveRecord = false;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getDefinition(): array {
        if ($this->__fieldDefinition == null) {
            $this->__fieldDefinition = [];
            $this->__pdoDefinition = [];
            for ($c = 0; $c < $this->__statement->columnCount(); $c++) {
                $def = $this->__statement->getColumnMeta($c);
                $this->__pdoDefinition[] = $def;
                $this->__fieldDefinition[] = FieldDefinition::fromColumnMetadata($def, $def['name']);
            }
        }
        return $this->__fieldDefinition;
    }

    public function getPdoDefinition(): array {
        if ($this->__pdoDefinition == null) {
            $this->getDefinition();
        }
        return $this->__pdoDefinition;
    }

    public function getRecord(): array|null {
        if ($this->__fields !== false) {
            $fields = [];
            foreach ($this->getDefinition() as $fieldDef) {
                $fields[$fieldDef->getName()] = $this->__fields[$fieldDef->getName()];
            }
            return $fields;
        }

        return null;
    }

    public function next(): bool {
        try {
            $this->__fields = $this->__statement->fetch();
            $this->__hasActiveRecord = $this->__fields === false ? false : true;
            return $this->hasActiveRecord();
        } catch (PDOException $e) {
            return false;
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

    public function jsonSerialize(): mixed
    {
        $records = $this->__statement->fetchAll();
        return [
            'records' => $records ?? [],
            'pdoDefinition' => $this->getPdoDefinition(),
            'fieldDefinition' => $this->getDefinition()
        ];
    }

    public function hasField(string $field): bool
    {
        return is_array($this->__fields) && array_key_exists($field, $this->__fields);
    }
}