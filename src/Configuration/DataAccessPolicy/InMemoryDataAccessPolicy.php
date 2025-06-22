<?php

namespace Amtgard\ActiveRecordOrm\Configuration\TablePolicy;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Interface\TablePolicy;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Optional\Optional;

class InMemoryTablePolicy implements TablePolicy
{
    private Database $database;
    private ActiveRecordOrmConfiguration $configuration;
    private array $tableSchema;
    private array $queries;

    public function __construct(Database $database, ActiveRecordOrmConfiguration $configuration) {
        $this->database = $database;
        $this->configuration = $configuration;
    }

    public function buildTableSchema(string $name): TableSchema
    {
        return Optional::ofNullable($this->tableSchema[$name])
            ->map(function($schemaDefinition) {
                return FromJsonTableSchema::builder()
                    ->definition($schemaDefinition)
                    ->build();
            })
            ->orElseGet(function() use ($name) {
                $this->tableSchema[$name] = UncachedTableSchema::builder()
                    ->tableName($name)
                    ->database($this->database)
                    ->build();
                return $this->tableSchema[$name];
        });
    }

    public function buildRecordSet(Database $database, QueryBuilder $queryBuilder): RecordSet
    {
        $queryHash = $queryBuilder->hash();
        return Optional::ofNullable($this->queries[$queryHash])
            ->map(function($serializedRecordSet) {
                return new RecordSet\InMemoryRecordSet($serializedRecordSet);
            })
            ->orElseGet(function() use ($queryHash, $queryBuilder) {
                $this->queries[$queryHash] = $queryBuilder;
                return $this->queries[$queryHash]->execute();
            });
    }
}