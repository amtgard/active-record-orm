<?php

namespace Amtgard\ActiveRecordOrm\Feature;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;

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
        return AuditTable::builder()
            ->srcTable(Table::builder()
                ->tableName($tableName)
                ->database($database)
                ->tableSchema($policy->applyTableSchemaPolicy($tableName))
                ->dataAccessPolicy($policy)
                ->queryBuilder(TableFactory::buildQueryBuilder($policy, $tableName))
                ->fieldSet(FieldSet::builder()->build())
                ->build())
            ->auditTable(Table::builder()
                ->tableName($auditTableName)
                ->database($database)
                ->tableSchema($policy->applyTableSchemaPolicy($auditTableName))
                ->dataAccessPolicy($policy)
                ->queryBuilder(TableFactory::buildQueryBuilder($policy, $auditTableName))
                ->fieldSet(FieldSet::builder()->build())
                ->build())
            ->byWhomSupplier(fn () => 0)
            ->build();
    }
}