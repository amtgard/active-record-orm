<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Optional\Optional;

class InMemoryDataAccessPolicy implements DataAccessPolicy
{
    private Database $database;
    private array $tableSchema;
    private array $queries;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function applyTableSchemaPolicy(string $name): TableSchema
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

    public function applyQueryPolicy(Query $query): RecordSet
    {
        $queryHash = $query->hash();
        return Optional::ofNullable($this->queries[$queryHash])
            ->map(function($serializedRecordSet) {
                return new RecordSet\InMemoryRecordSet($serializedRecordSet);
            })
            ->orElseGet(function() use ($queryHash, $query) {
                $jsonRecordSet = json_encode($this->database->executeQuery($query));
                $recordSet = new RecordSet\InMemoryRecordSet(json_encode($jsonRecordSet));
                $this->queries[$queryHash] = $jsonRecordSet;
                return $recordSet;
            });
    }
}