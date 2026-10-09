<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Cache\CacheLifetimeManagementPolicy;
use Amtgard\ActiveRecordOrm\Cache\InMemoryCache;
use Amtgard\ActiveRecordOrm\Cache\TtlCacheLifetimeManagementPolicy;
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\CachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Exception\SchemaCodecException;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchemaCodec;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Psr\SimpleCache\CacheInterface;
use Tests\Unit\Cache\FakeClock;
use Tests\Util\Constants;

class CachedDataAccessPolicyTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private Query $mockQuery;
    private CachedDataAccessPolicy $dataAccessPolicy;
    private CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockQuery = Phake::mock(Query::class);
        $this->mockDatabase = $this->database();
        $this->mockCache = Phake::mock(CacheInterface::class);
        $this->dataAccessPolicy = $this->policy($this->mockDatabase, $this->mockCache);

        Phake::when($this->mockDatabase)->execute(Phake::anyParameters())->thenReturnCallback(function () {
            return $this->describeRecordSet();
        });
    }

    public function testApplyTableSchemaPolicy_whenNotCached_createsUncachedTableSchema(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);

        $result = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertInstanceOf(TableSchema::class, $result);
        Phake::verify($this->mockDatabase)->clear();
        Phake::verify($this->mockCache)->get('amtgard_orm:testdb:schema:test_table');
        Phake::verify($this->mockCache)->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::capture($stored),
            43200
        );
        $decoded = $this->payload($stored);
        self::assertSame('test_table', $decoded['table']);
        self::assertSame('id', $decoded['primaryKey']);
        self::assertArrayNotHasKey('database', $decoded);
        self::assertArrayNotHasKey('value', $decoded['fields'][0]);
    }

    public function testApplyTableSchemaPolicy_memoHitReturnsTheSameInstanceWithoutTheCache(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);

        $first = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');
        $second = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertSame($first, $second);
        Phake::verify($this->mockCache, Phake::times(1))->get(Phake::anyParameters());
        Phake::verify($this->mockCache, Phake::times(1))->set(Phake::anyParameters());
        Phake::verify($this->mockDatabase, Phake::times(1))->clear();
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
    }

    public function testApplyTableSchemaPolicy_whenCached_returnsFromJsonTableSchema(): void
    {
        $cache = InMemoryCache::builder()->build();
        $policy = $this->policy($this->mockDatabase, $cache);

        $firstResult = $policy->applyTableSchemaPolicy('test_table');
        self::assertInstanceOf(UncachedTableSchema::class, $firstResult);

        $reader = $this->policy($this->mockDatabase, $cache);
        $secondResult = $reader->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(FromJsonTableSchema::class, $secondResult);
        self::assertSame($firstResult->getTableName(), $secondResult->getTableName());
        self::assertSame(count($firstResult->getFields()), count($secondResult->getFields()));
        self::assertSame($firstResult->getPrimaryKey()->getName(), $secondResult->getPrimaryKey()->getName());
        self::assertSame($firstResult->getField('id')->getType(), $secondResult->getField('id')->getType());
        self::assertSame($firstResult->getField('id')->getExtra(), $secondResult->getField('id')->getExtra());
        self::assertSame($firstResult->getField('id')->getNullable(), $secondResult->getField('id')->getNullable());
        self::assertSame($firstResult->getField('string_value')->getNullable(), $secondResult->getField('string_value')->getNullable());
        self::assertSame('auto_increment', $secondResult->getField('id')->getExtra());
        self::assertFalse($secondResult->getField('id')->getNullable());
        self::assertTrue($secondResult->getField('string_value')->getNullable());
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
        Phake::verify($this->mockDatabase, Phake::times(1))->clear();
    }

    public function testApplyTableSchemaPolicy_cacheHitDoesNotClearTheDatabase(): void
    {
        $cache = InMemoryCache::builder()->build();
        $this->policy($this->mockDatabase, $cache)->applyTableSchemaPolicy('test_table');

        $readerDatabase = $this->database();
        $hydrated = $this->policy($readerDatabase, $cache)->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(FromJsonTableSchema::class, $hydrated);
        Phake::verify($readerDatabase, Phake::never())->clear();
        Phake::verify($readerDatabase, Phake::never())->execute(Phake::anyParameters());
    }

    public function testApplyTableSchemaPolicy_hydratesTheRequestedTableName(): void
    {
        $cache = InMemoryCache::builder()->build();
        $this->policy($this->mockDatabase, $cache)->applyTableSchemaPolicy('test_table');
        $key = 'amtgard_orm:testdb:schema:test_table';
        $wrapper = json_decode($cache->get($key), true, 512, JSON_THROW_ON_ERROR);
        $document = json_decode($wrapper['payload'], true, 512, JSON_THROW_ON_ERROR);
        $document['table'] = 'other_table';
        $wrapper['payload'] = json_encode($document, JSON_THROW_ON_ERROR);
        $cache->set($key, json_encode($wrapper, JSON_THROW_ON_ERROR));

        $hydrated = $this->policy($this->mockDatabase, $cache)->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(FromJsonTableSchema::class, $hydrated);
        self::assertSame('test_table', $hydrated->getTableName());
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
    }

    public function testApplyTableSchemaPolicy_whenDocumentIsTruncated_describesAgain(): void
    {
        $wrapper = json_encode(['payload' => '{', 'expiresAt' => null], JSON_THROW_ON_ERROR);
        Phake::when($this->mockCache)->get('amtgard_orm:testdb:schema:test_table')->thenReturn($wrapper);

        $result = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertSame('id', $result->getPrimaryKey()->getName());
        self::assertSame('auto_increment', $result->getField('id')->getExtra());
        Phake::verify($this->mockDatabase)->execute('describe test_table');
        Phake::verify($this->mockCache)->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::capture($stored),
            Phake::ignoreRemaining()
        );
        $decoded = $this->payload($stored);
        self::assertSame('test_table', $decoded['table']);
        self::assertSame('id', $decoded['primaryKey']);
        self::assertArrayNotHasKey('database', $decoded);
        self::assertArrayNotHasKey('value', $decoded['fields'][0]);
    }

    public function testApplyTableSchemaPolicy_whenCachedValueIsNotAString_describesAgain(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(['fields' => []]);

        $result = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertSame('id', $result->getPrimaryKey()->getName());
        Phake::verify($this->mockDatabase)->execute('describe test_table');
        Phake::verify($this->mockCache)->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::capture($stored),
            Phake::ignoreRemaining()
        );
        self::assertIsString($stored);
        $decoded = $this->payload($stored);
        self::assertSame('test_table', $decoded['table']);
    }

    public function testApplyTableSchemaPolicy_whenWrapperIsCorrupt_describesAgain(): void
    {
        $corrupt = [
            '{',
            'null',
            '[]',
            '"document"',
            json_encode(['expiresAt' => null], JSON_THROW_ON_ERROR),
            json_encode(['payload' => ['table' => 'test_table'], 'expiresAt' => null], JSON_THROW_ON_ERROR),
            json_encode(['payload' => '{}'], JSON_THROW_ON_ERROR),
            json_encode(['payload' => '{}', 'expiresAt' => 'soon'], JSON_THROW_ON_ERROR),
            json_encode(['payload' => '{}', 'expiresAt' => 1.5], JSON_THROW_ON_ERROR),
        ];
        foreach ($corrupt as $stored) {
            $cache = Phake::mock(CacheInterface::class);
            $database = $this->database();
            Phake::when($database)->execute(Phake::anyParameters())->thenReturn($this->describeRecordSet());
            Phake::when($cache)->get(Phake::anyParameters())->thenReturn($stored);
            $result = $this->policy($database, $cache)->applyTableSchemaPolicy('test_table');
            self::assertInstanceOf(UncachedTableSchema::class, $result);
            Phake::verify($database)->execute('describe test_table');
        }
    }

    public function testApplyTableSchemaPolicy_withDifferentTableNames_createsSeparateCaches(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);

        $result1 = $this->dataAccessPolicy->applyTableSchemaPolicy('table_one');
        $result2 = $this->dataAccessPolicy->applyTableSchemaPolicy('table_two');

        self::assertInstanceOf(UncachedTableSchema::class, $result1);
        self::assertInstanceOf(UncachedTableSchema::class, $result2);
        self::assertNotSame($result1, $result2);
        self::assertSame($result1, $this->dataAccessPolicy->applyTableSchemaPolicy('table_one'));
        self::assertSame($result2, $this->dataAccessPolicy->applyTableSchemaPolicy('table_two'));
        Phake::verify($this->mockCache)->get('amtgard_orm:testdb:schema:table_one');
        Phake::verify($this->mockCache)->get('amtgard_orm:testdb:schema:table_two');
    }

    public function testApplyTableSchemaPolicy_memoKeySeparatesDatabaseNameFromTableName(): void
    {
        $databaseName = 'ab';
        $tableName = 'c';
        Phake::when($this->mockDatabase)->getDatabaseName()->thenReturnCallback(function () use (&$databaseName) {
            return $databaseName;
        });
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);

        $first = $this->dataAccessPolicy->applyTableSchemaPolicy($tableName);
        $databaseName = 'a';
        $second = $this->dataAccessPolicy->applyTableSchemaPolicy('bc');

        self::assertNotSame($first, $second);
        Phake::verify($this->mockDatabase)->execute('describe c');
        Phake::verify($this->mockDatabase)->execute('describe bc');
    }

    public function testApplyTableSchemaPolicy_memoKeyIncludesTheDatabaseName(): void
    {
        $databaseName = 'alpha';
        Phake::when($this->mockDatabase)->getDatabaseName()->thenReturnCallback(function () use (&$databaseName) {
            return $databaseName;
        });
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);

        $first = $this->dataAccessPolicy->applyTableSchemaPolicy('users');
        $databaseName = 'beta';
        $second = $this->dataAccessPolicy->applyTableSchemaPolicy('users');

        self::assertNotSame($first, $second);
        Phake::verify($this->mockCache)->get('amtgard_orm:alpha:schema:users');
        Phake::verify($this->mockCache)->get('amtgard_orm:beta:schema:users');
        Phake::verify($this->mockDatabase, Phake::times(2))->execute('describe users');
    }

    public function testApplyTableSchemaPolicy_withCustomSchemaKeyNameSupplier_usesCustomKey(): void
    {
        $seen = null;
        $schemaKeyNameSupplier = function ($tableName) use (&$seen) {
            $seen = $tableName;
            return 'custom_schema_key_' . $tableName;
        };
        $dataAccessPolicy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->schemaKeyNameSupplier($schemaKeyNameSupplier)
            ->build();
        Phake::when($this->mockCache)->get('custom_schema_key_custom_table')->thenReturn(null);

        $result = $dataAccessPolicy->applyTableSchemaPolicy('custom_table');

        self::assertSame('custom_table', $seen);
        Phake::verify($this->mockCache)->get('custom_schema_key_custom_table');
        Phake::verify($this->mockCache)->set(
            'custom_schema_key_custom_table',
            Phake::capture($stored),
            Phake::ignoreRemaining()
        );
        self::assertInstanceOf(UncachedTableSchema::class, $result);
        $decoded = $this->payload($stored);
        self::assertSame('custom_table', $decoded['table']);
        self::assertArrayNotHasKey('database', $decoded);
    }

    public function testApplyTableSchemaPolicy_nonCallableSupplierUsesTheDefaultKey(): void
    {
        $dataAccessPolicy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->schemaKeyNameSupplier('not-a-callable')
            ->build();
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);

        $dataAccessPolicy->applyTableSchemaPolicy('custom_table');

        Phake::verify($this->mockCache)->get('amtgard_orm:testdb:schema:custom_table');
    }

    public function testApplyTableSchemaPolicy_passesExpiryAsTheCacheTtlAndStoresExpiresAt(): void
    {
        $clock = new FakeClock(1_000);
        $lifetime = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($lifetime)->expiry(Phake::anyParameters())->thenReturn(60);
        Phake::when($lifetime)->isExpired(Phake::anyParameters())->thenReturn(false);
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturn(true);
        $policy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->lifetimePolicy($lifetime)
            ->build();

        $policy->applyTableSchemaPolicy('test_table');

        Phake::verify($lifetime)->expiry(Phake::capture($subject));
        Phake::verify($lifetime)->onStore(Phake::capture($storedSubject), Phake::capture($envelope));
        Phake::verify($this->mockCache)->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::capture($stored),
            60
        );
        $wrapper = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('testdb', $subject->getDatabaseName());
        self::assertSame('schema', $subject->getEntryKind());
        self::assertSame('test_table', $subject->getKeyId());
        self::assertSame('testdb', $storedSubject->getDatabaseName());
        self::assertSame('schema', $storedSubject->getEntryKind());
        self::assertSame('test_table', $storedSubject->getKeyId());
        self::assertSame(1_060, $wrapper['expiresAt']);
        self::assertSame($wrapper['payload'], $envelope->getPayload());
        self::assertSame(1_060, $envelope->getExpiresAt());
        self::assertSame('test_table', $this->payload($stored)['table']);
    }

    public function testApplyTableSchemaPolicy_dateIntervalTtlBecomesUnixExpiry(): void
    {
        $clock = new FakeClock(1_000);
        $ttl = new \DateInterval('PT15M');
        $lifetime = TtlCacheLifetimeManagementPolicy::builder()->clock($clock)->ttl($ttl)->build();
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturn(true);
        $policy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->lifetimePolicy($lifetime)
            ->build();

        $policy->applyTableSchemaPolicy('test_table');

        Phake::verify($this->mockCache)->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::capture($stored),
            $ttl
        );
        $wrapper = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1_900, $wrapper['expiresAt']);
    }

    public function testApplyTableSchemaPolicy_nullTtlStoresNullExpiryAndStillHits(): void
    {
        $clock = new FakeClock(1_000);
        $lifetime = TtlCacheLifetimeManagementPolicy::builder()->clock($clock)->ttl(null)->build();
        $stored = null;
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturnCallback(
            function ($key, $value) use (&$stored) {
                $stored = $value;
                return true;
            }
        );
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturnCallback(function () use (&$stored) {
            return $stored;
        });
        $writer = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->lifetimePolicy($lifetime)
            ->build();
        $writer->applyTableSchemaPolicy('test_table');

        $reader = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->lifetimePolicy($lifetime)
            ->build();
        $hydrated = $reader->applyTableSchemaPolicy('test_table');

        $wrapper = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($wrapper['expiresAt']);
        self::assertInstanceOf(FromJsonTableSchema::class, $hydrated);
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
        Phake::verify($this->mockCache)->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::capture($storedValue),
            Phake::capture($ttl)
        );
        self::assertNull($ttl);
        self::assertIsString($storedValue);
    }

    public function testApplyTableSchemaPolicy_expiredEnvelopeIsAMissWithoutAdapterTtl(): void
    {
        $clock = new FakeClock(2_000_000_000);
        $stored = null;
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturnCallback(
            function ($key, $value) use (&$stored) {
                $stored = $value;
                return true;
            }
        );
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturnCallback(function () use (&$stored) {
            return $stored;
        });
        $writer = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->build();
        $writer->applyTableSchemaPolicy('test_table');
        $wrapper = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2_000_043_200, $wrapper['expiresAt']);

        $clock->advance(43_200);
        $reader = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->build();
        $result = $reader->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        Phake::verify($this->mockDatabase, Phake::times(2))->execute('describe test_table');
        Phake::verify($this->mockDatabase, Phake::times(2))->clear();
    }

    public function testApplyTableSchemaPolicy_unexpiredEnvelopeIsAHit(): void
    {
        $clock = new FakeClock(2_000_000_000);
        $stored = null;
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturnCallback(
            function ($key, $value) use (&$stored) {
                $stored = $value;
                return true;
            }
        );
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturnCallback(function () use (&$stored) {
            return $stored;
        });
        CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        $clock->advance(43_199);
        $hydrated = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->clock($clock)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(FromJsonTableSchema::class, $hydrated);
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
    }

    public function testApplyTableSchemaPolicy_policyCanRejectAnEnvelopeTheClockWouldKeep(): void
    {
        $stored = null;
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturnCallback(
            function ($key, $value) use (&$stored) {
                $stored = $value;
                return true;
            }
        );
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturnCallback(function () use (&$stored) {
            return $stored;
        });
        $lifetime = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($lifetime)->expiry(Phake::anyParameters())->thenReturn(null);
        Phake::when($lifetime)->isExpired(Phake::anyParameters())->thenReturn(true);
        $policy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->lifetimePolicy($lifetime)
            ->build();
        $policy->applyTableSchemaPolicy('test_table');
        $stored = json_encode(['payload' => $this->payloadString($stored), 'expiresAt' => null], JSON_THROW_ON_ERROR);

        $readerLifetime = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($readerLifetime)->expiry(Phake::anyParameters())->thenReturn(null);
        Phake::when($readerLifetime)->isExpired(Phake::anyParameters())->thenReturn(true);
        $result = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->lifetimePolicy($readerLifetime)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        Phake::verify($readerLifetime)->isExpired(Phake::anyParameters());
        Phake::verify($this->mockDatabase, Phake::times(2))->execute('describe test_table');
    }

    public function testApplyTableSchemaPolicy_getThrowingStillReturnsTheSchema(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenThrow(new \RuntimeException('cache down'));
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturn(true);

        $result = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertSame('id', $result->getPrimaryKey()->getName());
        Phake::verify($this->mockCache)->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::capture($stored),
            Phake::ignoreRemaining()
        );
        self::assertIsString($stored);
        self::assertStringNotContainsString('cache down', $stored);
        $decoded = $this->payload($stored);
        self::assertSame('test_table', $decoded['table']);
    }

    public function testApplyTableSchemaPolicy_setThrowingStillReturnsTheSchema(): void
    {
        $lifetime = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($lifetime)->expiry(Phake::anyParameters())->thenReturn(60);
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenThrow(new \RuntimeException('cache full'));
        $policy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->lifetimePolicy($lifetime)
            ->build();

        $result = $policy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertSame('id', $result->getPrimaryKey()->getName());
        Phake::verify($lifetime, Phake::never())->onStore(Phake::anyParameters());
    }

    public function testApplyTableSchemaPolicy_setReturningFalseDoesNotCallOnStore(): void
    {
        $lifetime = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($lifetime)->expiry(Phake::anyParameters())->thenReturn(60);
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);
        Phake::when($this->mockCache)->set(Phake::anyParameters())->thenReturn(false);
        $policy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->lifetimePolicy($lifetime)
            ->build();

        $result = $policy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        Phake::verify($lifetime, Phake::never())->onStore(Phake::anyParameters());
    }

    public function testApplyTableSchemaPolicy_failedEncodeDoesNotOverwriteAGoodEntry(): void
    {
        $cache = InMemoryCache::builder()->build();
        $this->policy($this->mockDatabase, $cache)->applyTableSchemaPolicy('test_table');
        $key = 'amtgard_orm:testdb:schema:test_table';
        $before = $cache->get($key);

        $codec = Phake::mock(TableSchemaCodec::class);
        Phake::when($codec)->decode(Phake::anyParameters())->thenThrow(new SchemaCodecException('rejected'));
        Phake::when($codec)->encode(Phake::anyParameters())->thenThrow(new \RuntimeException('encode failed'));
        $result = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($cache)
            ->schemaCodec($codec)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertSame($before, $cache->get($key));
    }

    public function testApplyQueryPolicy_executesTheQueryAndReturnsTheDatabaseRecordSet(): void
    {
        $mockRecordSet = Phake::mock(RecordSet::class);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($mockRecordSet);

        $result = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);

        Phake::verify($this->mockDatabase)->executeQuery($this->mockQuery);
        Phake::verify($this->mockQuery)->postQuery();
        Phake::verify($this->mockQuery, Phake::never())->hash();
        Phake::verify($mockRecordSet, Phake::never())->jsonSerialize();
        Phake::verify($this->mockCache, Phake::never())->get(Phake::anyParameters());
        Phake::verify($this->mockCache, Phake::never())->set(Phake::anyParameters());
        self::assertSame($mockRecordSet, $result);
    }

    public function testApplyQueryPolicy_executesEveryQueryAgainstTheDatabase(): void
    {
        $firstSet = Phake::mock(RecordSet::class);
        $secondSet = Phake::mock(RecordSet::class);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($firstSet)->thenReturn($secondSet);

        $first = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);
        $second = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);

        Phake::verify($this->mockDatabase, Phake::times(2))->executeQuery($this->mockQuery);
        Phake::verify($this->mockQuery, Phake::times(2))->postQuery();
        Phake::verify($this->mockCache, Phake::never())->get(Phake::anyParameters());
        Phake::verify($this->mockCache, Phake::never())->set(Phake::anyParameters());
        self::assertSame($firstSet, $first);
        self::assertSame($secondSet, $second);
    }

    public function testApplyQueryPolicy_doesNotShareAWritePathWithSchemaEntries(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(null);
        $mockRecordSet = Phake::mock(RecordSet::class);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($mockRecordSet);

        $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');
        $result = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);

        self::assertSame($mockRecordSet, $result);
        Phake::verify($this->mockCache, Phake::times(1))->get(Phake::anyParameters());
        Phake::verify($this->mockCache, Phake::times(1))->set(
            'amtgard_orm:testdb:schema:test_table',
            Phake::ignoreRemaining()
        );
    }

    private function database(): Database
    {
        $database = Phake::mock(Database::class);
        Phake::when($database)->getDatabaseName()->thenReturn('testdb');
        return $database;
    }

    private function policy(Database $database, CacheInterface $cache): CachedDataAccessPolicy
    {
        return CachedDataAccessPolicy::builder()
            ->database($database)
            ->cache($cache)
            ->build();
    }

    private function payload(string $stored): array
    {
        $document = json_decode($this->payloadString($stored), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        return $document;
    }

    private function payloadString(string $stored): string
    {
        $wrapper = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($wrapper);
        self::assertIsString($wrapper['payload']);
        return $wrapper['payload'];
    }

    private function describeRecordSet(): RecordSet
    {
        $statement = Phake::mock(\PDOStatement::class);
        $recordSet = new RecordSet\PdoRecordSet($statement);
        $columns = json_decode(Constants::$DESCRIBE_TABLE_INTEG, true);
        $fetch = Phake::when($statement)->fetch();
        foreach ($columns as $column) {
            $fetch = $fetch->thenReturn($column);
        }
        $fetch->thenReturn(false);
        return $recordSet;
    }
}
