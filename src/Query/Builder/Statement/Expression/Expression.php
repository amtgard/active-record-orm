<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;

abstract class Expression
{
    use Builder, Getter;

    protected TableSchema $schema;

    protected FieldSet $fieldSet;

    public function getTableSchema(): TableSchema
    {
        return $this->schema;
    }

    public function getFieldSet(): FieldSet
    {
        return $this->fieldSet;
    }

    public abstract function willEmit(): bool;

    public abstract function emit(): string;

    public abstract function preparedParameters(): array;
}