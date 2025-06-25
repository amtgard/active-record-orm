<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;

class TableFactory
{
    public static function build(Database $database, DataAccessPolicy $policy, string $tableName): Table {
        return Table::builder()
            ->tableName($tableName)
            ->database($database)
            ->tableSchema($policy->applyTableSchemaPolicy($tableName))
            ->dataAccessPolicy($policy)
            ->queryBuilder(TableFactory::buildQueryBuilder($policy, $tableName))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
    }

    public static function buildQueryBuilder(DataAccessPolicy $policy, string $tableName): QueryBuilder {
        return QueryBuilder::builder()
            ->tableSchema($policy->applyTableSchemaPolicy($tableName))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
    }
}