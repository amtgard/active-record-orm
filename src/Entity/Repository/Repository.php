<?php

namespace Amtgard\ActiveRecordOrm\Entity\Repository;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\EntityMapperInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Interface\QueryableInterface;
use Amtgard\ActiveRecordOrm\Trait\RepositoryTrait;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\ToBuilder;

abstract class Repository implements ActiveRecordTableInterface, EntityRepositoryInterface, EntityMapperInterface, QueryableInterface
{
    use Builder, ToBuilder, RepositoryTrait;

    protected EntityMapper $auditEntityMapper;


}