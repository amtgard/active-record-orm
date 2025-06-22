<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;

class DeleteExpression extends Expression
{

    public function willEmit(): bool
    {
        return true;
    }

    public function emit(): string
    {
        return "DELETE FROM " . $this->schema->getTableName();
    }

    public function preparedParameters(): array
    {
        return [];
    }
}