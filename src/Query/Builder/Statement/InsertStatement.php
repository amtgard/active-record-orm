<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\InsertExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

class InsertStatement extends Statement
{
    use Builder;
    use Data;

    private InsertExpression $insertExpression;

    public function orderedExpressionMap(): array
    {
        return [
            $this->insertExpression
        ];
    }

}