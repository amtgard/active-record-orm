<?php

namespace Amtgard\ActiveRecordOrm\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class ListOf
{
    public function __construct(string $name = null) {
    }

}