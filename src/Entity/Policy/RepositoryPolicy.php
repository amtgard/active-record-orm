<?php

namespace Amtgard\ActiveRecordOrm\Entity\Policy;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Interface\TableQueryInterface;
use Amtgard\ActiveRecordOrm\Table;

abstract class RepositoryPolicy
{
    abstract public function flushEntity(EntityMapper $mapper, Entity $entity);
}