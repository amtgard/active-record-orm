<?php

use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;

#[RepositoryOf("audit_source", AuditSourceRepositoryEntity::class)]
class AuditSourceRepository extends Repository
{
    public static function getTableName()
    {
        return 'audit_source';
    }

    public static function getEntityClass()
    {
        return AuditSourceRepositoryEntity::class;
    }
}