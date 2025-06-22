<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\InsertExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\UpdateExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\InsertStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\UpdateStatement;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

class UpsertStatementBuilder extends StatementBuilder
{
    use Builder;
    use Data;

    private ?string $alias = null;

    public function getStatement(): Statement
    {
        if ($this->tableSchema->primaryKeyIsSet($this->fieldSet)) {
            return UpdateStatement::builder()
            ->tableSchema($this->tableSchema)
            ->updateExpression(UpdateExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->build())
            ->whereExpression(WhereExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet->subSet([$this->tableSchema->getPrimaryKey()->getName()]))->build())
            ->build();
        } else {
            return InsertStatement::builder()
                ->tableSchema($this->tableSchema)
                ->insertExpression(InsertExpression::builder()->schema($this->tableSchema)->fieldSet($this->fieldSet)->build())
                ->postQueryCallback($this->postQueryCallback)
                ->build();
        }
    }
}