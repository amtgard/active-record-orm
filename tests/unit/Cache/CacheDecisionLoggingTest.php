<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\CacheEnvelope;
use Amtgard\ActiveRecordOrm\Cache\CacheLifetimeManagementPolicy;
use Amtgard\ActiveRecordOrm\Cache\CacheSubject;
use Amtgard\ActiveRecordOrm\Cache\CompositeCacheLifetimeManagementPolicy;
use Amtgard\ActiveRecordOrm\Cache\InMemoryCache;
use Amtgard\ActiveRecordOrm\Cache\TtlCacheLifetimeManagementPolicy;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class CacheDecisionLoggingTest extends AmtgardTestCase
{
    public function testLogsExpiryChosenAndEntryTreatedAsExpired(): void
    {
        $logger = new RecordingLogger();
        $clock = new FakeClock(1_700_000_000);
        $subject = CacheSubject::builder()
            ->databaseName('ork')
            ->entryKind('schema')
            ->keyId('users')
            ->build();
        $fresh = CacheEnvelope::builder()
            ->payload('fresh')
            ->expiresAt(1_700_000_100)
            ->build();
        $open = CacheEnvelope::builder()
            ->payload('open')
            ->expiresAt(null)
            ->build();
        $stale = CacheEnvelope::builder()
            ->payload('stale')
            ->expiresAt(1_700_000_000)
            ->build();

        $ttl = TtlCacheLifetimeManagementPolicy::builder()
            ->clock($clock)
            ->logger($logger)
            ->ttl(60)
            ->build();
        $ttl->expiry($subject);
        self::assertCount(1, $logger->records);
        $ttl->isExpired($subject, $open);
        $ttl->isExpired($subject, $fresh);
        self::assertCount(1, $logger->records);
        $ttl->isExpired($subject, $stale);
        self::assertCount(2, $logger->records);

        $child = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($child)->expiry(Phake::anyParameters())->thenReturn(30);
        Phake::when($child)->isExpired(Phake::anyParameters())->thenReturn(false)->thenReturn(true);

        $composite = CompositeCacheLifetimeManagementPolicy::builder()
            ->logger($logger)
            ->policies([$child])
            ->build();
        $composite->expiry($subject);
        self::assertCount(3, $logger->records);
        $composite->isExpired($subject, $fresh);
        self::assertCount(3, $logger->records);
        $composite->isExpired($subject, $stale);
        self::assertCount(4, $logger->records);

        $cache = InMemoryCache::builder()
            ->clock($clock)
            ->logger($logger)
            ->build();
        $cache->set('users', 'document', 5);
        $cache->get('users');
        $clock->advance(5);
        $cache->has('users');
        $cache->has('users');
        self::assertCount(5, $logger->records);

        $cache->set('ephemeral', 'value', 0);
        $cache->set('ephemeral', 'value', -1);
        $negativeInterval = new \DateInterval('PT5S');
        $negativeInterval->invert = 1;
        $cache->set('ephemeral', 'value', $negativeInterval);
        $cache->set('ephemeral', 'value', new \DateInterval('PT0S'));
        $cache->has('ephemeral');
        $cache->get('ephemeral', 'default');

        self::assertSame([
            [
                'level' => 'debug',
                'message' => 'expiry chosen',
                'context' => [
                    'database' => 'ork',
                    'entryKind' => 'schema',
                    'keyId' => 'users',
                    'ttl' => 60,
                ],
            ],
            [
                'level' => 'info',
                'message' => 'entry treated as expired',
                'context' => [
                    'database' => 'ork',
                    'entryKind' => 'schema',
                    'keyId' => 'users',
                ],
            ],
            [
                'level' => 'debug',
                'message' => 'expiry chosen',
                'context' => [
                    'database' => 'ork',
                    'entryKind' => 'schema',
                    'keyId' => 'users',
                    'ttl' => 30,
                ],
            ],
            [
                'level' => 'info',
                'message' => 'entry treated as expired',
                'context' => [
                    'database' => 'ork',
                    'entryKind' => 'schema',
                    'keyId' => 'users',
                ],
            ],
            [
                'level' => 'info',
                'message' => 'entry treated as expired',
                'context' => [
                    'key' => 'users',
                ],
            ],
        ], $logger->records);
    }
}
