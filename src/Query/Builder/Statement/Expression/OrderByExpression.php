<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Setter;

class OrderByExpression extends Expression
{
    use Builder, Setter;

    /* @var Operation */
    private array $orderByOperations = [];

    public function willEmit(): bool
    {
        return count($this->orderByOperations) > 0;
    }

    public function emit(): string
    {
        return "ORDER BY " . implode(', ',
                array_map(
                    fn($field, $orderBy) => $field . ' ' . $orderBy->value,
                    array_keys($this->orderByOperations), $this->orderByOperations));
    }

    public function preparedParameters(): array
    {
        return [];
    }
}