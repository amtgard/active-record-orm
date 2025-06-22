<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Optional\Optional;
use Psr\SimpleCache\CacheInterface;

class RemoteCacheDataAccessPolicy implements DataAccessPolicy
{
    private Database $database;
    private array $tableSchema;
    private CacheInterface $cache;

    public function __construct(Database $database, CacheInterface $cache) {
        $this->database = $database;
        $this->cache = $cache;
    }

    public function applyTableSchemaPolicy(string $name): TableSchema
    {
        return Optional::ofNullable($this->cache->get("amtgard_orm_table_schema_$name"))
            ->map(function($schemaDefinition) {
                return FromJsonTableSchema::builder()
                    ->definition($schemaDefinition)
                    ->build();
            })
            ->orElseGet(function() use ($name) {
                return Optional::ofNullable($this->tableSchema[$name])->orElseGet(function() use ($name) {
                    $this->tableSchema[$name] = UncachedTableSchema::builder()
                        ->tableName($name)
                        ->database($this->database)
                        ->build();
                    return $this->tableSchema[$name];
                });
            });
    }

    public function applyQueryPolicy(Query $query): RecordSet
    {
        $queryHash = $query->hash();
        return Optional::ofNullable($this->cache->get($queryHash))
            ->map(function($serializedRecordSet) {
                return new RecordSet\InMemoryRecordSet($serializedRecordSet);
            })
            ->orElseGet(function() use ($queryHash, $query) {
                $jsonRecordSet = json_encode($this->database->executeQuery($query));
                $query->postQuery();
                $recordSet = new RecordSet\InMemoryRecordSet(json_encode($jsonRecordSet));
                $this->cache->set($queryHash, $jsonRecordSet);
                return $recordSet;
            });
    }
}