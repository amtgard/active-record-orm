<?php

namespace Tests\Util;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Tests\Util\TestRepository;

#[EntityOf(TestRepository::class)]
class TestRepositoryEntity extends RepositoryEntity
{
    use Builder, ToBuilder, Data;
}
