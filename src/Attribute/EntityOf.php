<?php

namespace Amtgard\ActiveRecordOrm\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class EntityOf
{
    public function __construct(string $name) {
    }
}