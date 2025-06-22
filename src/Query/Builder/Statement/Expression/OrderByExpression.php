<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\Traits\Builder\Getter;
use OrderBy;

class OrderByExpression extends Expression
{
    use Getter;

    public function addOrdering(string $field, OrderBy $ordering): void {
        $this->clauses[] = "$field $ordering";
    }
}