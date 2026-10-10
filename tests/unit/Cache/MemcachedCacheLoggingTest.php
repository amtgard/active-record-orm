<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\MemcachedCache;
use Amtgard\PHPUnit\AmtgardTestCase;

class MemcachedCacheLoggingTest extends AmtgardTestCase
{
    public function testLogsMemcachedGetFailure(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeMemcachedClient();
        $clock = new FakeClock(1_000);
        $cache = MemcachedCache::builder()
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
                'message' => 'Memcached get failure',
                'context' => ['key' => 'schema'],
            ],
        ], $logger->records);
    }

    public function testLogsMemcachedSetFailure(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeMemcachedClient();
        $cache = MemcachedCache::builder()
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
                'message' => 'Memcached set failure',
                'context' => ['key' => 'schema'],
            ],
        ], $logger->records);
    }
}
