<?php

namespace Tests\Util;

use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Tests\Util\TestRepositoryEntity;

#[RepositoryOf("test_table", TestRepositoryEntity::class)]
class TestRepository extends Repository
{
    public static function getTableName()
    {
        return 'test_table';
    }

    public static function getEntityClass()
    {
        return TestRepositoryEntity::class;
    }
}
