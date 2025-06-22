<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\SelectExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\SelectStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\Traits\Builder\Data;

class FindBuilder extends Builder
{
    use \Amtgard\Traits\Builder\Builder;
    use Data;

    public function getStatement(): Statement
    {
        return SelectStatement::builder()
            ->tableSchema($this->tableSchema)
            ->selectExpression(SelectExpression::builder()->build())
            ->build();
    }
}