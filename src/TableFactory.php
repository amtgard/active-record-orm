<?php

namespace Amtgard\ActiveRecordOrm\Configuration;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Interface\TablePolicy;
use Amtgard\ActiveRecordOrm\Table;

class TableFactory
{
    public static function build(Database $database, TablePolicy $policy, string $tableName): Table {
        return Table::builder()
            ->database($database)
            ->schema($policy->getTableSchema($tableName))
            ->build();
    }
}