<?php

namespace Amtgard\ActiveRecordOrm\Factory;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Optional\Optional;

class TableFactory
{
    public static function build(Database $database, DataAccessPolicy $policy, string $tableName): Table {
        $tableSchema = $policy->applyTableSchemaPolicy($tableName);
        return Table::builder()
            ->tableName($tableName)
            ->database($database)
            ->tableSchema($tableSchema)
            ->dataAccessPolicy($policy)
            ->queryBuilder(TableFactory::buildQueryBuilder($policy, $tableName, $tableSchema))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
    }

    public static function buildQueryBuilder(DataAccessPolicy $policy, string $tableName, ?TableSchema $tableSchema = null): QueryBuilder {
        $schema = Optional::ofNullable($tableSchema)
            ->orElseGet(fn () => $policy->applyTableSchemaPolicy($tableName));
        return QueryBuilder::builder()
            ->tableSchema($schema)
            ->fieldSet(FieldSet::builder()->build())
            ->build();
    }
}
