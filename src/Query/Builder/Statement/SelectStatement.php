<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\SelectExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\FromExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\LimitExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\OrderByExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

// https://dev.mysql.com/doc/refman/8.4/en/select.html
class SelectStatement extends Statement
{
    use Builder;
    use Data;

    private ?string $alias;
    private SelectExpression $selectExpression;
    private FromExpression $fromExpression;
    private WhereExpression $whereExpression;
    private OrderByExpression $orderBy;
    private LimitExpression $limit;

    public function orderedExpressionMap(): array
    {
        return [
            $this->selectExpression,
            $this->fromExpression,
            $this->whereExpression,
            $this->orderBy,
            $this->limit
        ];
    }
}