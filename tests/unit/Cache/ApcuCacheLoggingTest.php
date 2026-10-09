<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\ApcuCache;
use Amtgard\PHPUnit\AmtgardTestCase;

class ApcuCacheLoggingTest extends AmtgardTestCase
{
    public function testLogsApcuGetFailureTreatedAsAMiss(): void
    {
        $logger = new RecordingLogger();
        $store = new FakeApcuStore();
        $cache = ApcuCache::builder()
            ->store($store)
            ->clock(new FakeClock(1_000))
            ->logger($logger)
            ->build();

        $cache->set('schema', 'document', null);
        $cache->get('schema');
        self::assertSame([], $logger->records);

        $store->fetchReturnsFalse = true;
        self::assertSame('default', $cache->get('schema', 'default'));

        $store->fetchReturnsFalse = false;
        $store->fetchThrows = true;
        self::assertSame('default', $cache->get('schema', 'default'));

        self::assertSame([
            [
                'level' => 'info',
                'message' => 'APCu get failure treated as a miss',
                'context' => ['key' => 'schema'],
            ],
            [
                'level' => 'info',
                'message' => 'APCu get failure treated as a miss',
                'context' => ['key' => 'schema'],
            ],
        ], $logger->records);
    }
}
