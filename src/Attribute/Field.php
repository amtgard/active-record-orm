<?php

namespace Amtgard\ActiveRecordOrm\Attribute;

use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Field
{
    public function __construct(string $name = null, string $reference = null) {
    }
}