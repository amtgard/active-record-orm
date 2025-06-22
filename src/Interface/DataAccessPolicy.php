<?php

namespace Amtgard\ActiveRecordOrm\Interface;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;

interface QueryCachePolicy
{
    public function buildTableSchema(string $name): TableSchema|null;

    public function buildRecordSet(Query $buildData): RecordSet|null;

}