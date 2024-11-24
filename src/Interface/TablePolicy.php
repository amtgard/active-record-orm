<?php

namespace Amtgard\ActiveRecordOrm\Interface;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;

interface TablePolicy
{
    public function getTableSchema(string $name): TableSchema|null;

    public function execute(Database $database, QueryBuilder $queryBuilder): RecordSet;

}