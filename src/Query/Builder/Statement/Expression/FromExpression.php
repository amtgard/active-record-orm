<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\Traits\Builder\Builder;

class FromExpression extends Expression
{
    use Builder;

    public function willEmit(): bool
    {
        return true;
    }

    public function emit(): string
    {
        return 'FROM ' . $this->schema->getTableName();
    }

    public function preparedParameters(): array
    {
        return [];
    }
}