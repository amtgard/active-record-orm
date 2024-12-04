<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\QueryPart;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;
use Amtgard\Traits\Builder\Setter;

// https://dev.mysql.com/doc/refman/8.4/en/select.html
class Select extends Statement
{
    use Builder;
    use Getter;
    use Setter;

    private array $selectExpr;
    private QueryPart $whereCondition;
    private false|array $groupBy;
    private array $orderBy;
    private false|array $limit;

}