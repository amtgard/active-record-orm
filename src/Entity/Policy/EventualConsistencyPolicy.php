<?php

namespace Amtgard\ActiveRecordOrm\Entity\Policy;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;
use Psr\SimpleCache\CacheInterface;

class EventualConsistencyPolicy extends RepositoryPolicy
{
    use Builder;
    private CacheInterface $cache;

    public function persist(EntityMapper $mapper, EntityInterface $entity): EntityInterface
    {
        return $entity;
    }
}