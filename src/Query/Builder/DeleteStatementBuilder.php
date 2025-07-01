<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\DeleteStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\DeleteExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\UpdateExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

class DeleteStatementBuilder extends StatementBuilder
{
    use Builder;
    use Data;

    private ?string $alias = null;

    public function getStatement(): Statement
    {
        $where = WhereExpression::builder()->schema($this->tableSchema);
        if ($this->getFieldSet()->hasField($this->tableSchema->getPrimaryKey()->getName())) {
            $where->fieldSet($this->fieldSet->subSet([$this->tableSchema->getPrimaryKey()->getName()]));
        } else {
            $where->fieldSet($this->fieldSet);
        }
        return DeleteStatement::builder()
            ->tableSchema($this->tableSchema)
            ->deleteExpression(DeleteExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->build())
            ->whereExpression($where->build())
            ->build();
    }
}