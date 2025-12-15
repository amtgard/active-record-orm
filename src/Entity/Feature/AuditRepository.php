<?php

namespace Amtgard\ActiveRecordOrm\Entity\Feature;

use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityMapperInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Interface\QueryableInterface;
use Amtgard\ActiveRecordOrm\Trait\AuditRepositoryTrait;
use Amtgard\ActiveRecordOrm\Trait\RepositoryTrait;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\ToBuilder;

class AuditRepository implements ActiveRecordTableInterface, EntityRepositoryInterface, EntityMapperInterface, QueryableInterface
{
    use Builder, ToBuilder, RepositoryTrait, AuditRepositoryTrait {
        AuditRepositoryTrait::postInit insteadof RepositoryTrait;
    }

}