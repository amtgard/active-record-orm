<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;

class Statement
{
    use Builder;
    use Getter;

    protected TableSchema $tableSchema;
    protected $aliasPrefix = 1;
    protected $postQueryCallback;

    protected function orderedExpressionMap(): array {
        return [];
    }

    public function buildSql(): string {
        $sql = implode(' ', array_map(fn($e) => $e->willEmit() ? $e->emit() : null, $this->orderedExpressionMap()));
        return $sql;
    }

    public function getStatementParams(): array {
        $parameters = array_merge(...array_map(fn($e) => $e->willEmit() ? $e->preparedParameters() : [], $this->orderedExpressionMap()));
        return $parameters;
    }

}