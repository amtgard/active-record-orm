<?php

namespace Tests\Util;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;

#[EntityOf(ConcreteRepository::class)]
class ConcreteRepositoryEntity extends RepositoryEntity
{
    use Builder, ToBuilder, Data;

}