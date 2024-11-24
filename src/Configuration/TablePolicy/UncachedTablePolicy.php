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
    private TablePolicyConfiguration $configuration;
    private array $tableSchema;

    public function __construct(Database $database, TablePolicyConfiguration $configuration) {
        $this->database = $database;
        $this->configuration = $configuration;
    }

    public function getTableSchema(string $name): TableSchema|null
    {
        return $this->tableSchema[$name] ?? null;
    }

    public function execute(Database $database, QueryBuilder $queryBuilder): RecordSet
    {
        return new RecordSet();
    }
}