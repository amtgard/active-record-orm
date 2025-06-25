<?php

namespace Amtgard\ActiveRecordOrm\Interface;

use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;

interface DataAccessPolicy
{
    public function applyTableSchemaPolicy(string $name): TableSchema|null;

    public function applyQueryPolicy(Query $query): RecordSet|null;

}