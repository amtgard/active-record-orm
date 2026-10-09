<?php

namespace Amtgard\ActiveRecordOrm\Factory;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Feature\AuditTable;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Table;

class AuditTableFactory extends TableFactory
{
    public static function auditMapperSupplier(Database $database, DataAccessPolicy $policy, string $mapperName) {
        return EntityMapper::builder()
            ->table(AuditTableFactory::build(
                $database,
                $policy,
                $mapperName))
            ->name($mapperName)
            ->build();
    }
    public static function build(Database $database, DataAccessPolicy $policy, string $tableName): Table
    {
        $auditTableName = $tableName . "_audit_log";
        $sourceSchema = $policy->applyTableSchemaPolicy($tableName);
        $auditSchema = $policy->applyTableSchemaPolicy($auditTableName);
        $srcTable = Table::builder()
            ->tableName($tableName)
            ->database($database)
            ->tableSchema($sourceSchema)
            ->dataAccessPolicy($policy)
            ->queryBuilder(TableFactory::buildQueryBuilder($policy, $tableName, $sourceSchema))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
        $auditTable = Table::builder()
            ->tableName($auditTableName)
            ->database($database)
            ->tableSchema($auditSchema)
            ->dataAccessPolicy($policy)
            ->queryBuilder(TableFactory::buildQueryBuilder($policy, $auditTableName, $auditSchema))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
        return AuditTable::builder()
            ->srcTable($srcTable)
            ->auditTable($auditTable)
            ->byWhomSupplier(fn () => 0)
            ->tableName($tableName)
            ->database($database)
            ->tableSchema($auditSchema)
            ->dataAccessPolicy($policy)
            ->queryBuilder(TableFactory::buildQueryBuilder($policy, $auditTableName, $auditSchema))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
    }
}