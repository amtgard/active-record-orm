<?php

namespace Amtgard\ActiveRecordOrm\Entity\Repository;

use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Trait\RepositoryEntityTrait;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;

abstract class RepositoryEntity implements EntityInterface
{
    use Builder, ToBuilder, Data, RepositoryEntityTrait;

}