<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;

class Expression
{
    use Builder;
    use Getter;

    protected FieldSet $fieldSet;
    protected Table $table;

    public function expressionWillProduce(): bool {
        throw new NotImplementedException();
    }
}