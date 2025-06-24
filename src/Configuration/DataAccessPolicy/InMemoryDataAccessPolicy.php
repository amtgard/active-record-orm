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
use Amtgard\Traits\Builder\Builder;
use Optional\Optional;

class InMemoryDataAccessPolicy implements DataAccessPolicy
{
    use Builder;

    private Database $database;
    private array $tableSchema = [];
    private array $queries = [];

    private function __construct() {

    }

    public function applyTableSchemaPolicy(string $name): TableSchema
    {
        return Optional::ofNullable($this->tableSchema[$name])
            ->map(function($schemaDefinition) use ($name) {
                return FromJsonTableSchema::builder()
                    ->jsonDefinition($schemaDefinition)
                    ->tableName($name)
                    ->database($this->database)
                    ->build();
            })
            ->orElseGet(function() use ($name) {
                $schema = UncachedTableSchema::builder()
                    ->tableName($name)
                    ->database($this->database)
                    ->build();
                $this->tableSchema[$name] = json_encode($schema);
                return $schema;
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
                $recordSet = new RecordSet\InMemoryRecordSet($jsonRecordSet);
                $this->queries[$queryHash] = $jsonRecordSet;
                return $recordSet;
            });
    }
}