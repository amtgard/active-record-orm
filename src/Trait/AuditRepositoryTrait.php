<?php

namespace Amtgard\ActiveRecordOrm\Trait;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\Traits\Builder\PostInit;

trait AuditRepositoryTrait
{
    protected EntityMapper $auditEntityMapper;
    protected string $auditTableName;
    #[PostInit]
    protected function postInit() {
        $this->initRepositoryOf();
        $repositoryEntityClass = $this->repositoryEntityClass;
        $this->entityMapInfo = $repositoryEntityClass::buildEntityMapInfo();

        $this->auditTableName = static::getTableName() . "_audit_log";
        $this->auditEntityMapper = EntityManager::getManager()->getMapper($this->auditTableName);
    }

    private function getAuditTableName() {
        return $this->tableName . "_audit";
    }
}