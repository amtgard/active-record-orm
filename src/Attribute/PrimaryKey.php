<?php

namespace Amtgard\ActiveRecordOrm\Attribute;

use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey
{
    public $name;
    public function __construct(string $name = null) {
        $this->name = $name;
    }
}