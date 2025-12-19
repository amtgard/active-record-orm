<?php

namespace Amtgard\ActiveRecordOrm\Feature\Entity\Repository;

use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Factory\AuditTableFactory;
use Optional\Optional;

trait AuditRepositoryEntityTrait
{
    private static ?EntityManager $em = null;
    protected function getEntityManager(): EntityManager {
        return Optional::ofNullable(static::$em)
            ->orElseGet(function() {
                static::$em = EntityManager::builder()
                    ->database(EntityManager::getManager()->getDatabase())
                    ->dataAccessPolicy(EntityManager::getManager()->getDataAccessPolicy())
                    ->repositoryPolicy(UncachedPolicy::builder()->build())
                    ->mapperSupplier(fn ($db, $policy, $tableName) => AuditTableFactory::auditMapperSupplier($db, $policy, $tableName))
                    ->build();
                return static::$em;
            });
    }
}