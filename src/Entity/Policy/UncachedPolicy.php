<?php

namespace Amtgard\ActiveRecordOrm\Entity\Policy;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Interface\TableQueryInterface;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;

class UncachedPolicy extends RepositoryPolicy
{
    use Builder;

    public function flushEntity(EntityMapper $mapper, Entity $entity)
    {
        $entity->flush($mapper);
    }
}