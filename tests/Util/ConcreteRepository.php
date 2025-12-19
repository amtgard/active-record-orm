<?php

namespace Tests\Util;

use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;

#[RepositoryOf("test_table", ConcreteRepositoryEntity::class)]
class ConcreteRepository extends Repository implements EntityRepositoryInterface
{
    public static function getTableName()
    {
        return 'test_table';
    }

    public static function getEntityClass()
    {
        return ConcreteRepositoryEntity::class;
    }
}