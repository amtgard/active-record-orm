<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\ActiveRecordOrm\Query\Builder\QueryPart;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;

class Statement
{
    use Builder;
    use Getter;

    protected QueryPart $principal;
    protected Table $table;
    protected int $tableNum = 1;
    public function buildSql(): string {
        throw new NotImplementedException();
    }
}