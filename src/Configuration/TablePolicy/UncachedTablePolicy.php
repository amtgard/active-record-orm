<?php

namespace Amtgard\ActiveRecordOrm\Configuration\TablePolicy;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Interface\TablePolicy;
use Amtgard\ActiveRecordOrm\Interface\TablePolicyConfiguration;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;

class UncachedTablePolicy implements TablePolicy
{

    private Database $database;

    public function __construct(Database $database, TablePolicyConfiguration $configuration) {
        $this->database = $database;
    }

    public function getTableSchema(string $name): TableSchema
    {
        return new TableSchema($this->database, $name);
    }

    public function execute(Database $database, QueryBuilder $queryBuilder): RecordSet
    {
        $queryBuilder->compile();
        return $queryBuilder->execute();
    }
}