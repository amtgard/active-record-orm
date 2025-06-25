<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\Getter;
use Amtgard\Traits\Builder\Setter;
use Optional\Optional;

abstract class StatementBuilder
{
    use Builder;
    use Data;

    protected TableSchema $tableSchema;
    protected FieldSet $fieldSet;
    protected $primaryKey;
    protected $postQueryCallback;

    public function getStatement(): Statement {
        throw new NotImplementedException();
    }

}