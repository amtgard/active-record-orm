<?php

namespace Amtgard\ActiveRecordOrm\Factory;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityMapperInterface;

class EntityFactory
{
    public static function build(EntityMapperInterface $mapper): EntityInterface {
        return Entity::builder()
            ->schema($mapper->getTable()->getTableSchema())
            ->mapper($mapper)
            ->changes($mapper->getChanges())
            ->build();
    }
}