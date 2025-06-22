<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\UpdateExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

class UpdateStatement extends Statement
{
    use Builder, Data;

    private ?string $alias;
    private UpdateExpression $updateExpression;
    private WhereExpression $whereExpression;

    public function orderedExpressionMap(): array
    {
        return [
            $this->updateExpression,
            $this->whereExpression
        ];
    }
}