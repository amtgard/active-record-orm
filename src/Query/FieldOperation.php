<?php

namespace Amtgard\ActiveRecordOrm\Query;

use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\Getter;

class FieldOperation
{
    use Builder;
    use Data;

    protected FieldDefinition $field;
    protected Operation $operation;
    protected $value;

}