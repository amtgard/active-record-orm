<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Optional\Optional;
use Psr\SimpleCache\CacheInterface;

class CachedDataAccessPolicy implements DataAccessPolicy
{
    use Builder;

    private Database $database;
    private CacheInterface $cache;
    private $schemaKeyNameSupplier = null;

    private function __construct() { }

    private function callSchemaKeyNameSupplier($tableName) {
        if (isset($this->schemaKeyNameSupplier) && is_callable($this->schemaKeyNameSupplier)) {
            return call_user_func($this->schemaKeyNameSupplier, $tableName);
        } else {
            return "amtgard_orm_table_schema_$tableName";
        }
    }

    public function applyTableSchemaPolicy(string $name): TableSchema
    {
        $schemaKey = $this->callSchemaKeyNameSupplier($name);
        return Optional::ofNullable($this->cache->get($schemaKey))
            ->map(function($schemaDefinition) use ($name) {
                return FromJsonTableSchema::builder()
                    ->jsonDefinition($schemaDefinition)
                    ->tableName($name)
                    ->database($this->database)
                    ->build();
            })
            ->orElseGet(function() use ($name, $schemaKey) {
                $schema = UncachedTableSchema::builder()
                    ->tableName($name)
                    ->database($this->database)
                    ->build();
                $this->cache->set($schemaKey, json_encode($schema));
                return $schema;
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
                $recordSet = new RecordSet\InMemoryRecordSet($jsonRecordSet);
                $this->cache->set($queryHash, $jsonRecordSet);
                return $recordSet;
            });
    }
}