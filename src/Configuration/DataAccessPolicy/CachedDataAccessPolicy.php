<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Cache\CacheEnvelope;
use Amtgard\ActiveRecordOrm\Cache\CacheLifetimeManagementPolicy;
use Amtgard\ActiveRecordOrm\Cache\CacheSubject;
use Amtgard\ActiveRecordOrm\Cache\Clock;
use Amtgard\ActiveRecordOrm\Cache\SystemClock;
use Amtgard\ActiveRecordOrm\Cache\TtlCacheLifetimeManagementPolicy;
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

/** Strategy */
class CachedDataAccessPolicy implements DataAccessPolicy
{
    use Builder;

    private Database $database;

    private CacheInterface $cache;

    private $schemaKeyNameSupplier = null;

    private TableSchemaCodec $schemaCodec;

    private LoggerInterface $logger;

    private CacheLifetimeManagementPolicy $lifetimePolicy;

    private Clock $clock;

    /** @var array<string, TableSchema> */
    private array $memo = [];

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->schemaCodec = Optional::ofNullable(isset($this->schemaCodec) ? $this->schemaCodec : null)
            ->orElseGet(fn () => TableSchemaCodec::builder()->build());
        $this->logger = Optional::ofNullable(isset($this->logger) ? $this->logger : null)
            ->orElseGet(fn () => new NullLogger());
        $this->clock = Optional::ofNullable(isset($this->clock) ? $this->clock : null)
            ->orElseGet(fn () => SystemClock::builder()->build());
        $this->lifetimePolicy = Optional::ofNullable(isset($this->lifetimePolicy) ? $this->lifetimePolicy : null)
            ->orElseGet(fn () => TtlCacheLifetimeManagementPolicy::builder()->clock($this->clock)->build());
    }

    public function applyTableSchemaPolicy(string $name): TableSchema
    {
        $memoKey = $this->memoKey($name);
        if (isset($this->memo[$memoKey])) {
            $this->logger->debug('memo hit', $this->context($name, $this->schemaKey($name)));
            return $this->memo[$memoKey];
        }
        $schema = $this->schemaFromCacheOrDatabase($name);
        $this->memo[$memoKey] = $schema;
        return $schema;
    }

    public function applyQueryPolicy(Query $query): RecordSet
    {
        $result = $this->database->executeQuery($query);
        $query->postQuery();
        return $result;
    }

    private function schemaFromCacheOrDatabase(string $name): TableSchema
    {
        $schemaKey = $this->schemaKey($name);
        $subject = $this->schemaSubject($name);
        $read = $this->readCachedSchema($name, $schemaKey, $subject);
        if ($read['hit'] !== null) {
            $this->logger->debug('cache hit', $this->context($name, $schemaKey));
            return $read['hit'];
        }
        if ($read['reportMiss']) {
            $this->logger->debug('cache miss', $this->context($name, $schemaKey));
        }
        return $this->loadUncachedSchema($name, $subject, $schemaKey);
    }

    /**
     * @return array{hit: TableSchema|null, reportMiss: bool}
     */
    private function readCachedSchema(string $name, string $schemaKey, CacheSubject $subject): array
    {
        try {
            $stored = $this->cache->get($schemaKey);
        } catch (\Throwable $exception) {
            $this->logger->info(
                'cache get failure',
                $this->failureContext($name, $schemaKey, $exception->getMessage())
            );
            return ['hit' => null, 'reportMiss' => false];
        }
        if ($stored === null) {
            return ['hit' => null, 'reportMiss' => true];
        }
        $envelope = $this->envelopeFromStored($stored);
        if ($envelope === null || $this->lifetimePolicy->isExpired($subject, $envelope)) {
            return ['hit' => null, 'reportMiss' => true];
        }
        $hydrated = $this->hydratePayload($name, $schemaKey, $envelope->getPayload());
        if ($hydrated === null) {
            return ['hit' => null, 'reportMiss' => false];
        }
        return ['hit' => $hydrated, 'reportMiss' => false];
    }

    private function hydratePayload(string $name, string $schemaKey, string $payload): ?TableSchema
    {
        try {
            return $this->hydrate($name, $this->schemaCodec->decode($payload));
        } catch (SchemaCodecException $exception) {
            $this->logger->info('codec failure treated as a miss', [
                'table' => $name,
                'schemaKey' => $schemaKey,
                'reason' => $exception->getMessage(),
            ]);
            return null;
        }
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

    private function loadUncachedSchema(string $name, CacheSubject $subject, string $schemaKey): TableSchema
    {
        $schema = UncachedTableSchema::builder()
            ->tableName($name)
            ->database($this->database)
            ->build();
        $this->storeSchema($name, $subject, $schemaKey, $schema);
        return $schema;
    }

    private function storeSchema(string $name, CacheSubject $subject, string $schemaKey, TableSchema $schema): void
    {
        $document = $this->encodeSchema($schema);
        if ($document === null) {
            return;
        }
        $ttl = $this->lifetimePolicy->expiry($subject);
        $envelope = CacheEnvelope::builder()
            ->payload($document)
            ->expiresAt($this->absoluteExpiry($ttl))
            ->build();
        try {
            $stored = $this->cache->set($schemaKey, $this->encodeWrapper($envelope), $ttl);
        } catch (\Throwable $exception) {
            $this->logger->info(
                'cache set failure',
                $this->failureContext($name, $schemaKey, $exception->getMessage())
            );
            return;
        }
        if ($stored) {
            $this->lifetimePolicy->onStore($subject, $envelope);
        }
    }

    private function encodeSchema(TableSchema $schema): ?string
    {
        try {
            return $this->schemaCodec->encode($schema);
        } catch (\Throwable) {
            return null;
        }
    }

    private function encodeWrapper(CacheEnvelope $envelope): string
    {
        return json_encode(
            [
                'payload' => $envelope->getPayload(),
                'expiresAt' => $envelope->getExpiresAt(),
            ],
            JSON_THROW_ON_ERROR
        );
    }

    private function envelopeFromStored(mixed $cached): ?CacheEnvelope
    {
        if (!is_string($cached)) {
            return null;
        }
        try {
            $decoded = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded) || !$this->wrapperIsValid($decoded)) {
            return null;
        }
        return CacheEnvelope::builder()
            ->payload($decoded['payload'])
            ->expiresAt($decoded['expiresAt'])
            ->build();
    }

    private function wrapperIsValid(array $decoded): bool
    {
        if (!array_key_exists('payload', $decoded) || !is_string($decoded['payload'])) {
            return false;
        }
        if (!array_key_exists('expiresAt', $decoded)) {
            return false;
        }
        return $decoded['expiresAt'] === null || is_int($decoded['expiresAt']);
    }

    private function absoluteExpiry(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable('@' . $this->clock->now()))
                ->add(clone $ttl)
                ->getTimestamp();
        }
        return $this->clock->now() + $ttl;
    }

    private function schemaKey(string $tableName): string
    {
        if (isset($this->schemaKeyNameSupplier) && is_callable($this->schemaKeyNameSupplier)) {
            return call_user_func($this->schemaKeyNameSupplier, $tableName);
        }
        return 'amtgard_orm:' . $this->database->getDatabaseName() . ':schema:' . $tableName;
    }

    private function schemaSubject(string $tableName): CacheSubject
    {
        return CacheSubject::builder()
            ->databaseName($this->database->getDatabaseName())
            ->entryKind('schema')
            ->keyId($tableName)
            ->build();
    }

    private function memoKey(string $tableName): string
    {
        return $this->database->getDatabaseName() . "\0" . $tableName;
    }

    private function context(string $table, string $schemaKey): array
    {
        return [
            'database' => $this->database->getDatabaseName(),
            'table' => $table,
            'schemaKey' => $schemaKey,
        ];
    }

    private function failureContext(string $table, string $schemaKey, string $reason): array
    {
        $context = $this->context($table, $schemaKey);
        $context['reason'] = $reason;
        return $context;
    }
}
