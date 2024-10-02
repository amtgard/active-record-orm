<?php

namespace Amtgard\ActiveRecordOrm\Interface\Table;

class FieldOperation
{
    private FieldDefinition $field;
    private Operation $operation;

    public function __construct(FieldDefinition $field, Operation $operation)
    {
        $this->field = $field;
        $this->operation = $operation;
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function getField(): FieldDefinition
    {
        return $this->field;
    }

}