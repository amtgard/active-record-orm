<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Cache\InMemoryCache;
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\CachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Psr\SimpleCache\CacheInterface;
use Tests\Unit\Cache\FakeClock;
use Tests\Unit\Cache\RecordingLogger;
use Tests\Util\Constants;

class CachedDataAccessPolicyLoggingTest extends AmtgardTestCase
{
    public function testLogsMemoHit(): void
    {
        $logger = new RecordingLogger();
        $policy = $this->policy($logger, $this->cacheReturning(null));

        $policy->applyTableSchemaPolicy('test_table');
        $logger->records = [];
        $policy->applyTableSchemaPolicy('test_table');

        self::assertSame([
            [
                'level' => 'debug',
                'message' => 'memo hit',
                'context' => $this->context('test_table'),
            ],
        ], $logger->records);
    }

    public function testLogsCacheHit(): void
    {
        $cache = InMemoryCache::builder()->build();
        $database = $this->database();
        CachedDataAccessPolicy::builder()
            ->database($database)
            ->cache($cache)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        $logger = new RecordingLogger();
        CachedDataAccessPolicy::builder()
            ->database($database)
            ->cache($cache)
            ->logger($logger)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        self::assertSame([
            [
                'level' => 'debug',
                'message' => 'cache hit',
                'context' => $this->context('test_table'),
            ],
        ], $logger->records);
    }

    public function testLogsCacheMiss(): void
    {
        $logger = new RecordingLogger();
        $cache = $this->cacheReturning(null);
        $this->policy($logger, $cache)->applyTableSchemaPolicy('test_table');

        self::assertSame([
            [
                'level' => 'debug',
                'message' => 'cache miss',
                'context' => $this->context('test_table'),
            ],
        ], $logger->records);
    }

    public function testLogsCacheMissWhenTheEnvelopeIsExpired(): void
    {
        $clock = new FakeClock(2_000_000_000);
        $stored = null;
        $cache = Phake::mock(CacheInterface::class);
        Phake::when($cache)->set(Phake::anyParameters())->thenReturnCallback(
            function ($key, $value) use (&$stored) {
                $stored = $value;
                return true;
            }
        );
        Phake::when($cache)->get(Phake::anyParameters())->thenReturnCallback(function () use (&$stored) {
            return $stored;
        });
        $database = $this->database();
        CachedDataAccessPolicy::builder()
            ->database($database)
            ->cache($cache)
            ->clock($clock)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        $clock->advance(43_200);
        $logger = new RecordingLogger();
        CachedDataAccessPolicy::builder()
            ->database($database)
            ->cache($cache)
            ->clock($clock)
            ->logger($logger)
            ->build()
            ->applyTableSchemaPolicy('test_table');

        self::assertSame([
            [
                'level' => 'debug',
                'message' => 'cache miss',
                'context' => $this->context('test_table'),
            ],
        ], $logger->records);
    }

    public function testLogsCacheGetFailure(): void
    {
        $logger = new RecordingLogger();
        $cache = Phake::mock(CacheInterface::class);
        Phake::when($cache)->get(Phake::anyParameters())->thenThrow(new \RuntimeException('cache down'));
        Phake::when($cache)->set(Phake::anyParameters())->thenReturn(true);

        $this->policy($logger, $cache)->applyTableSchemaPolicy('test_table');

        self::assertSame([
            [
                'level' => 'info',
                'message' => 'cache get failure',
                'context' => $this->failureContext('test_table', 'cache down'),
            ],
        ], $logger->records);
    }

    public function testLogsCacheSetFailure(): void
    {
        $logger = new RecordingLogger();
        $cache = Phake::mock(CacheInterface::class);
        Phake::when($cache)->get(Phake::anyParameters())->thenReturn(null);
        Phake::when($cache)->set(Phake::anyParameters())->thenThrow(new \RuntimeException('cache full'));

        $this->policy($logger, $cache)->applyTableSchemaPolicy('test_table');

        self::assertSame([
            [
                'level' => 'debug',
                'message' => 'cache miss',
                'context' => $this->context('test_table'),
            ],
            [
                'level' => 'info',
                'message' => 'cache set failure',
                'context' => $this->failureContext('test_table', 'cache full'),
            ],
        ], $logger->records);
    }

    public function testLogsCodecFailureTreatedAsMiss(): void
    {
        $logger = new RecordingLogger();
        $cache = Phake::mock(CacheInterface::class);
        $wrapper = json_encode(['payload' => '{', 'expiresAt' => null], JSON_THROW_ON_ERROR);
        Phake::when($cache)->get(Phake::anyParameters())->thenReturn($wrapper);
        Phake::when($cache)->set(Phake::anyParameters())->thenReturn(true);

        $this->policy($logger, $cache)->applyTableSchemaPolicy('test_table');

        self::assertSame([
            [
                'level' => 'info',
                'message' => 'codec failure treated as a miss',
                'context' => [
                    'table' => 'test_table',
                    'schemaKey' => 'amtgard_orm:testdb:schema:test_table',
                    'reason' => 'schema document is not valid JSON',
                ],
            ],
        ], $logger->records);
    }

    private function policy(RecordingLogger $logger, CacheInterface $cache): CachedDataAccessPolicy
    {
        return CachedDataAccessPolicy::builder()
            ->database($this->database())
            ->cache($cache)
            ->logger($logger)
            ->build();
    }

    private function cacheReturning(mixed $value): CacheInterface
    {
        $cache = Phake::mock(CacheInterface::class);
        Phake::when($cache)->get(Phake::anyParameters())->thenReturn($value);
        Phake::when($cache)->set(Phake::anyParameters())->thenReturn(true);
        return $cache;
    }

    private function database(): Database
    {
        $database = Phake::mock(Database::class);
        Phake::when($database)->getDatabaseName()->thenReturn('testdb');
        Phake::when($database)->execute(Phake::anyParameters())->thenReturnCallback(function () {
            return $this->describeRecordSet();
        });
        return $database;
    }

    /**
     * @return array{database: string, table: string, schemaKey: string}
     */
    private function context(string $table): array
    {
        return [
            'database' => 'testdb',
            'table' => $table,
            'schemaKey' => 'amtgard_orm:testdb:schema:' . $table,
        ];
    }

    /**
     * @return array{database: string, table: string, schemaKey: string, reason: string}
     */
    private function failureContext(string $table, string $reason): array
    {
        return $this->context($table) + ['reason' => $reason];
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
