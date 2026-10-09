<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Exception\SchemaCodecException;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchemaCodec;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class CachedDataAccessPolicy implements DataAccessPolicy
{
    use Builder;

    private Database $database;
    private CacheInterface $cache;
    private $schemaKeyNameSupplier = null;
    private TableSchemaCodec $schemaCodec;
    private LoggerInterface $logger;

    private function __construct() { }

    #[PostInit]
    private function postInit(): void
    {
        $this->schemaCodec = TableSchemaCodec::builder()->build();
        $this->logger = Optional::ofNullable(isset($this->logger) ? $this->logger : null)
            ->orElseGet(fn () => new NullLogger());
    }

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
            ->map(fn (mixed $cached) => $this->hydrateCachedDocument($name, $schemaKey, $cached))
            ->orElseGet(fn () => $this->loadUncachedSchema($name, $schemaKey));
    }

    private function hydrateCachedDocument(string $name, string $schemaKey, mixed $cached): ?TableSchema
    {
        try {
            return $this->hydrate($name, $this->schemaCodec->decode($this->documentString($cached)));
        } catch (SchemaCodecException $exception) {
            $this->logger->info('codec failure treated as a miss', [
                'table' => $name,
                'schemaKey' => $schemaKey,
                'reason' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function documentString(mixed $cached): string
    {
        if (is_string($cached)) {
            return $cached;
        }
        throw new SchemaCodecException('schema document must be a string');
    }

    private function hydrate(string $name, array $decoded): TableSchema
    {
        return FromJsonTableSchema::builder()
            ->decodedFields($decoded['fields'])
            ->primaryKeyName($decoded['primaryKey'])
            ->tableName($name)
            ->database($this->database)
            ->build();
    }

    private function loadUncachedSchema(string $name, string $schemaKey): TableSchema
    {
        $schema = UncachedTableSchema::builder()
            ->tableName($name)
            ->database($this->database)
            ->build();
        $this->cache->set($schemaKey, $this->schemaCodec->encode($schema));
        return $schema;
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
