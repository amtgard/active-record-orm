<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;
use Amtgard\Traits\Builder\Setter;

class OrderByExpression extends Expression
{
    use Builder, Setter;

    public function willEmit(): bool
    {
        return false;
    }

    public function emit(): string
    {
        return "ORDER BY ";
    }

    public function preparedParameters(): array
    {
        return [];
    }
}