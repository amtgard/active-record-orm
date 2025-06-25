<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\SelectExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\FromExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\LimitExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\OrderByExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\SelectStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

class FindStatementBuilder extends StatementBuilder
{
    use Builder;
    use Data;

    private ?string $alias = null;

    private bool $isCount = false;
    private string $countAlias = 'row_count';

    protected bool $withLimit = false;
    protected int $offset = 0;
    protected ?int $rowCount = null;

    protected array $orderByOperations = [];

    public function getStatement(): Statement
    {
        return SelectStatement::builder()
            ->tableSchema($this->tableSchema)
            ->alias($this->alias)
            ->selectExpression(SelectExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->isCount($this->isCount)->countAlias($this->countAlias)->build())
            ->fromExpression(FromExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->build())
            ->whereExpression(WhereExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->build())
            ->orderBy(OrderByExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->orderByOperations($this->orderByOperations)->build())
            ->limit(LimitExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->withLimit($this->withLimit)->offset($this->offset)->rowCount($this->rowCount)->build())
            ->build();
    }
}