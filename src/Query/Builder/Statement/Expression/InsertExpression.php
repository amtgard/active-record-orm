<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;

class InsertExpression extends Expression
{
    public function willEmit(): bool
    {
        return true;
    }

    public function emit(): string
    {
        $fields = $this->getFieldSet()->getFieldsByOperation([Operation::Set, Operation::Equals]);
        $fieldNames = array_map(fn($f) => $f->getField()->getName(), $fields);
        $fieldValues = array_map(fn($f) => ":" . $f->getField()->getName(), $fields);
        $fieldNameList = implode(', ', $fieldNames);
        $fieldValueList = implode(', ', $fieldValues);
        return "INSERT INTO " . $this->schema->getTableName() . " ($fieldNameList) VALUES ($fieldValueList)";
    }

    public function preparedParameters(): array
    {
        return $this->getFieldSet()->toKeyValueMap($this->getFieldSet()->getFieldsByOperation([Operation::Set, Operation::Equals]));
    }
}