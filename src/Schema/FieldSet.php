<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Query\FieldOperation;

class FieldSet
{
    /** @var FieldOperation[] */
    private array $fields = [];

    public function __construct() {
    }

    public function setField(FieldOperation $fieldOperation) {
        $this->fields[$fieldOperation->field->getName()] = $fieldOperation;
    }

    public function hasField(FieldDefinition $field)
    {
        return array_key_exists($field->getName(), $this->fields);
    }

    public function getField(string $fieldName) {
        return $this->fields[$fieldName];
    }

    /** @return string[] */
    public function getFieldNames(): array {
        return array_keys($this->fields);
    }
}