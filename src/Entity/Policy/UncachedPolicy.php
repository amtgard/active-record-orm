<?php

namespace Amtgard\ActiveRecordOrm\Entity\Policy;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;

class UncachedPolicy extends RepositoryPolicy
{
    use Builder;

    public function persist(EntityMapper $mapper, EntityInterface $entity)
    {
        $entity->persist($mapper);
    }
}