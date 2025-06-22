<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\DeleteExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

class DeleteStatement extends Statement
{
    use Builder, Data;

    private ?string $alias;
    private DeleteExpression $deleteExpression;
    private WhereExpression $whereExpression;

    public function orderedExpressionMap(): array
    {
        return [
            $this->deleteExpression,
            $this->whereExpression
        ];
    }
}