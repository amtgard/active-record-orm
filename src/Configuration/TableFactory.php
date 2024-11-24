<?php

namespace Amtgard\ActiveRecordOrm\Configuration;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Interface\TablePolicy;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use JJWare\Utils\Option\Option;

class TableFactory
{
    public static function build(Database $database, TablePolicy $policy, string $tableName): Table {
        return Option::nullable($policy->getTableSchema($tableName))
            ->getOrElseGet(function() use ($database, $tableName) {
                return new TableSchema($database, $tableName);
            });
    }
}