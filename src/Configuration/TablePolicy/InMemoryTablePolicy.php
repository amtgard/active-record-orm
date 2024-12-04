<?php

namespace Amtgard\ActiveRecordOrm\Configuration\TablePolicy;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Interface\TablePolicy;
use Amtgard\ActiveRecordOrm\Interface\TablePolicyConfiguration;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Optional\Optional;

class InMemoryTablePolicy implements TablePolicy
{
    private Database $database;
    private TablePolicyConfiguration $configuration;
    private array $tableSchema;
    private array $queries;

    public function __construct(Database $database, TablePolicyConfiguration $configuration) {
        $this->database = $database;
        $this->configuration = $configuration;
    }

    public function getTableSchema(string $name): TableSchema
    {
        return Optional::ofNullable($this->tableSchema[$name])->orElseGet(function() use ($name) {
            $this->tableSchema[$name] = new TableSchema($this->database, $name);
            return $this->tableSchema[$name];
        });
    }

    public function execute(Database $database, QueryBuilder $queryBuilder): RecordSet
    {
        $queryHash = $queryBuilder->hash();
        return Optional::ofNullable($this->queries[$queryHash])->orElseGet(function() use ($queryHash, $queryBuilder) {
            $this->queries[$queryHash] = $queryBuilder;
            $this->queries[$queryHash]->compile();
            return $this->queries[$queryHash]->execute();
        });
    }
}