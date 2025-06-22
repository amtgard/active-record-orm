<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;

class UpsertExpression extends Expression
{
    public function willEmit(): bool
    {
        return false;
    }

    public function emit(): string
    {
        return "";
    }

    public function preparedParameters(): array
    {
        return [];
    }
}