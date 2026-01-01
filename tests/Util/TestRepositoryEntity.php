<?php

namespace Tests\Util;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;
use Tests\Util\TestRepository;

#[EntityOf(TestRepository::class)]
class TestRepositoryEntity extends RepositoryEntity
{
    use Builder, ToBuilder, Data;

    #[Field("name")]
    protected string $name;
}
