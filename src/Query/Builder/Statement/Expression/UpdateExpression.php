<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;

class UpdateExpression extends Expression
{
    public function willEmit(): bool
    {
        return true;
    }

    public function emit(): string
    {
        $fields = $this->getFieldSet()->getFieldsByOperation([Operation::Set]);
        $fieldNames = array_map(fn($f) => $f->getField()->getName(), $fields);
        $fieldValues = array_map(fn($f) => ":" . $f->getField()->getName(), $fields);
        $fieldNameAssignmentPairs = implode(', ', array_map(fn($f, $v) => "$f = $v", $fieldNames, $fieldValues));
        return "UPDATE " . $this->schema->getTableName() . " SET $fieldNameAssignmentPairs";
    }

    public function preparedParameters(): array
    {
        return $this->getFieldSet()->toKeyValueMap($this->getFieldSet()->getFieldsByOperation([Operation::Set]));
    }
}