<?php

namespace Amtgard\ActiveRecordOrm\Query;

use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;

class FieldOperation
{
    public FieldDefinition $field;
    public Operation $operation;
    public $value;

    public function __construct(FieldDefinition $field, Operation $operation, $value) {
        $this->field = $field;
        $this->operation = $operation;
        $this->value = $value;
    }
}