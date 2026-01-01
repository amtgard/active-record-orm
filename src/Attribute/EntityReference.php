<?php

namespace Amtgard\ActiveRecordOrm\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class EntityReference
{
    public function __construct(
        public ?string $referenceProperty = null
    ) {
    }

}