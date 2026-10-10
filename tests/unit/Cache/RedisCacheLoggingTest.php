<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\RedisCache;
use Amtgard\PHPUnit\AmtgardTestCase;

class RedisCacheLoggingTest extends AmtgardTestCase
{
    public function testLogsRedisGetFailure(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeRedisClient();
        $clock = new FakeClock(1_000);
        $cache = RedisCache::builder()
            ->client($client)
            ->clock($clock)
            ->logger($logger)
            ->build();

        $cache->set('schema', 'document', null);
        $cache->get('schema');
        self::assertFalse($cache->has('missing'));
        self::assertSame('default', $cache->get('missing', 'default'));

        $cache->set('temp', 'value', 1);
        $clock->advance(1);
        self::assertSame('default', $cache->get('temp', 'default'));

        $client->getThrows = true;
        self::assertSame('default', $cache->get('schema', 'default'));

        self::assertSame([
            [
                'level' => 'info',
                'message' => 'Redis get failure',
                'context' => ['key' => 'schema'],
            ],
        ], $logger->records);
    }

    public function testLogsRedisSetFailure(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeRedisClient();
        $cache = RedisCache::builder()
            ->client($client)
            ->clock(new FakeClock(1_000))
            ->logger($logger)
            ->build();

        self::assertTrue($cache->set('schema', 'document', null));
        $client->setReturnsFalse = true;
        self::assertFalse($cache->set('schema', 'next', 10));

        $client->setReturnsFalse = false;
        $client->setThrows = true;
        self::assertFalse($cache->set('schema', 'broken', 10));
        self::assertSame('document', $cache->get('schema'));

        self::assertSame([
            [
                'level' => 'info',
                'message' => 'Redis set failure',
                'context' => ['key' => 'schema'],
            ],
        ], $logger->records);
    }
}
