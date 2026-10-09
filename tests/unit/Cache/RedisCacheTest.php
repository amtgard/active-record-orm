<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\NativeRedisClient;
use Amtgard\ActiveRecordOrm\Cache\RedisCache;
use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\PHPUnit\AmtgardTestCase;

class RedisCacheTest extends AmtgardTestCase
{
    public function testBuild_withoutClient_usesNativeRedisClient(): void
    {
        $cache = RedisCache::builder()->build();

        $client = (new \ReflectionProperty(RedisCache::class, 'client'))->getValue($cache);
        self::assertInstanceOf(NativeRedisClient::class, $client);
        self::assertFalse((new \ReflectionProperty(NativeRedisClient::class, 'redis'))->isInitialized($client));
    }

    public function testBuild_withoutClockOrLogger_storesAndReads(): void
    {
        $client = new FakeRedisClient();
        $cache = RedisCache::builder()->client($client)->build();

        self::assertTrue($cache->set('schema', 'document', null));
        self::assertNull($client->ttls['schema']);
        self::assertSame('document', $cache->get('schema'));
        self::assertTrue($cache->has('schema'));
    }

    public function testGet_withNonExistingKey_returnsDefault(): void
    {
        $cache = $this->cache(new FakeRedisClient());

        self::assertNull($cache->get('missing'));
        self::assertSame('default_value', $cache->get('missing', 'default_value'));
        self::assertFalse($cache->has('missing'));
    }

    public function testGet_whenClientReturnsFalse_returnsDefault(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('schema', 'document', null);
        $client->getReturnsFalse = true;

        self::assertSame('default', $cache->get('schema', 'default'));
        self::assertFalse($cache->has('schema'));
    }

    public function testGet_whenClientThrows_returnsDefault(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('schema', 'document', null);
        $client->getThrows = true;

        self::assertSame('default', $cache->get('schema', 'default'));
        self::assertFalse($cache->has('schema'));
    }

    public function testGet_withUnreadablePayload_returnsDefault(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $client->items['text'] = '"document"';
        $client->items['missing-value'] = '{"expiresAt":null}';
        $client->items['missing-expiry'] = '{"value":"document"}';
        $client->items['bad-expiry'] = '{"value":"document","expiresAt":"soon"}';
        $client->items['broken'] = '{';

        self::assertSame('default', $cache->get('text', 'default'));
        self::assertSame('default', $cache->get('missing-value', 'default'));
        self::assertSame('default', $cache->get('missing-expiry', 'default'));
        self::assertSame('default', $cache->get('bad-expiry', 'default'));
        self::assertSame('default', $cache->get('broken', 'default'));
        self::assertFalse($cache->has('text'));
    }

    public function testSet_withNewKey_storesEnvelopeAndRedisTtl(): void
    {
        $clock = new FakeClock(5_000);
        $client = new FakeRedisClient();
        $cache = $this->cache($client, $clock);
        $key = 'amtgard_orm:ork:schema:users';

        self::assertTrue($cache->set($key, 'document', 30));
        self::assertTrue($cache->has($key));
        self::assertSame('document', $cache->get($key));
        self::assertSame(['value' => 'document', 'expiresAt' => 5_030], $this->payload($client, $key));
        self::assertSame(30, $client->ttls[$key]);
    }

    public function testSet_withExistingKey_replacesValue(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('existing_key', 'initial_value', null);

        self::assertTrue($cache->set('existing_key', 'new_value', 15));
        self::assertSame('new_value', $cache->get('existing_key'));
        self::assertSame(15, $client->ttls['existing_key']);
    }

    public function testSet_isVisibleToAnotherAdapterOnTheSameClient(): void
    {
        $clock = new FakeClock(100);
        $client = new FakeRedisClient();
        $first = $this->cache($client, $clock);
        $second = $this->cache($client, $clock);
        $first->set('shared', 'value', null);

        self::assertSame('value', $second->get('shared'));
        self::assertTrue($second->has('shared'));
        self::assertNull($client->ttls['shared']);
    }

    public function testSet_withNullTtl_doesNotExpireAndPassesNoRedisTtl(): void
    {
        $clock = new FakeClock(1_000);
        $client = new FakeRedisClient();
        $cache = $this->cache($client, $clock);

        self::assertTrue($cache->set('durable', 'value', null));
        $clock->advance(100_000);

        self::assertTrue($cache->has('durable'));
        self::assertSame('value', $cache->get('durable'));
        self::assertNull($client->ttls['durable']);
        self::assertNull($this->payload($client, 'durable')['expiresAt']);
    }

    public function testSet_withZeroTtl_deletesKey(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('gone', 'value', null);
        $calls = $client->setCalls;

        self::assertTrue($cache->set('gone', 'replacement', 0));
        self::assertSame($calls, $client->setCalls);
        self::assertArrayNotHasKey('gone', $client->items);
        self::assertFalse($cache->has('gone'));
        self::assertSame('default', $cache->get('gone', 'default'));
    }

    public function testSet_withNegativeTtl_deletesKey(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('gone', 'value', null);

        self::assertTrue($cache->set('gone', 'replacement', -1));
        self::assertArrayNotHasKey('gone', $client->items);
        self::assertFalse($cache->has('gone'));
        self::assertNull($cache->get('gone'));
    }

    public function testSet_withNegativeTtlOnMissingKey_leavesKeyAbsent(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);

        self::assertTrue($cache->set('missing', 'value', -5));
        self::assertSame(0, $client->setCalls);
        self::assertArrayNotHasKey('missing', $client->items);
        self::assertFalse($cache->has('missing'));
    }

    public function testSet_withPositiveIntTtl_expiresAgainstClock(): void
    {
        $clock = new FakeClock(5_000);
        $client = new FakeRedisClient();
        $cache = $this->cache($client, $clock);

        self::assertTrue($cache->set('temp', 'value', 10));
        self::assertSame(10, $client->ttls['temp']);
        self::assertTrue($cache->has('temp'));

        $clock->advance(9);
        self::assertTrue($cache->has('temp'));
        self::assertSame('value', $cache->get('temp', 'default'));

        $clock->advance(1);
        self::assertFalse($cache->has('temp'));
        self::assertSame('default', $cache->get('temp', 'default'));
        self::assertArrayNotHasKey('temp', $client->items);
    }

    public function testSet_withDateIntervalTtl_expiresAgainstClockAndPassesPositiveRedisTtl(): void
    {
        $clock = new FakeClock(8_000);
        $client = new FakeRedisClient();
        $cache = $this->cache($client, $clock);
        $ttl = new \DateInterval('PT1H30M');

        self::assertTrue($cache->set('interval', 'value', $ttl));
        self::assertTrue($cache->set('again', 'other', $ttl));
        self::assertSame(5400, $client->ttls['interval']);
        self::assertSame(13_400, $this->payload($client, 'interval')['expiresAt']);

        $clock->advance(5399);
        self::assertTrue($cache->has('interval'));
        self::assertSame('other', $cache->get('again'));

        $clock->advance(1);
        self::assertFalse($cache->has('interval'));
        self::assertSame('default', $cache->get('again', 'default'));
        self::assertArrayNotHasKey('interval', $client->items);
    }

    public function testSet_withZeroDateInterval_deletesKey(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('zero', 'value', null);
        $calls = $client->setCalls;

        self::assertTrue($cache->set('zero', 'replacement', new \DateInterval('PT0S')));
        self::assertSame($calls, $client->setCalls);
        self::assertArrayNotHasKey('zero', $client->items);
        self::assertFalse($cache->has('zero'));
        self::assertSame('default', $cache->get('zero', 'default'));
    }

    public function testSet_withNegativeDateInterval_deletesKey(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('negative', 'value', null);
        $ttl = new \DateInterval('PT5S');
        $ttl->invert = 1;

        self::assertTrue($cache->set('negative', 'replacement', $ttl));
        self::assertArrayNotHasKey('negative', $client->items);
        self::assertFalse($cache->has('negative'));
    }

    public function testSet_withDayDateInterval_remainsUntilThatDayPasses(): void
    {
        $clock = new FakeClock(10_000);
        $client = new FakeRedisClient();
        $cache = $this->cache($client, $clock);

        self::assertTrue($cache->set('day', 'value', new \DateInterval('P1D')));
        self::assertSame(86400, $client->ttls['day']);
        $clock->advance(86399);
        self::assertTrue($cache->has('day'));

        $clock->advance(1);
        self::assertFalse($cache->has('day'));
    }

    public function testSet_afterExpiry_storesAFreshValue(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cache(new FakeRedisClient(), $clock);
        $cache->set('refill', 'old', 1);
        $clock->advance(1);
        $cache->set('refill', 'new', null);

        self::assertSame('new', $cache->get('refill', 'default'));
    }

    public function testSet_replacesTtlOfExistingKey(): void
    {
        $clock = new FakeClock(100);
        $client = new FakeRedisClient();
        $cache = $this->cache($client, $clock);
        $cache->set('replace', 'value', 5);
        $cache->set('replace', 'later', null);
        $clock->advance(50);

        self::assertSame('later', $cache->get('replace'));
        self::assertNull($client->ttls['replace']);
        self::assertNull($this->payload($client, 'replace')['expiresAt']);
    }

    public function testSet_storesNullFalseAndZero(): void
    {
        $cache = $this->cache(new FakeRedisClient());

        $cache->set('null', null, null);
        $cache->set('false', false, null);
        $cache->set('zero', 0, null);
        $cache->set('empty', '', null);

        self::assertTrue($cache->has('null'));
        self::assertNull($cache->get('null', 'default'));
        self::assertTrue($cache->has('false'));
        self::assertFalse($cache->get('false', 'default'));
        self::assertSame(0, $cache->get('zero', 1));
        self::assertSame('', $cache->get('empty', 'default'));
    }

    public function testSet_whenClientReturnsFalse_returnsFalse(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('schema', 'document', null);
        $client->setReturnsFalse = true;

        self::assertFalse($cache->set('schema', 'next', 10));
        self::assertSame('document', $cache->get('schema'));
        self::assertNull($client->ttls['schema']);
    }

    public function testSet_whenClientThrows_returnsFalse(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('schema', 'document', null);
        $client->setThrows = true;

        self::assertFalse($cache->set('schema', 'next', 10));
        self::assertSame('document', $cache->get('schema'));
    }

    public function testDelete_withExistingKey_removesValue(): void
    {
        $cache = $this->cache(new FakeRedisClient());
        $cache->set('delete_key', 'delete_value', null);

        self::assertTrue($cache->delete('delete_key'));
        self::assertFalse($cache->has('delete_key'));
    }

    public function testDelete_withNonExistingKey_returnsTrue(): void
    {
        $cache = $this->cache(new FakeRedisClient());

        self::assertTrue($cache->delete('missing'));
    }

    public function testDelete_whenClientFails_returnsFalse(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('schema', 'document', null);
        $client->deleteReturnsFalse = true;

        self::assertFalse($cache->delete('schema'));
        self::assertSame('document', $cache->get('schema'));
    }

    public function testClear_removesAllValues(): void
    {
        $cache = $this->cache(new FakeRedisClient());
        $cache->set('key1', 'value1', null);
        $cache->set('key2', 'value2', null);

        self::assertTrue($cache->clear());
        self::assertFalse($cache->has('key1'));
        self::assertFalse($cache->has('key2'));
    }

    public function testClear_whenClientFails_returnsFalse(): void
    {
        $client = new FakeRedisClient();
        $cache = $this->cache($client);
        $cache->set('key1', 'value1', null);
        $client->clearReturnsFalse = true;

        self::assertFalse($cache->clear());
        self::assertSame('value1', $cache->get('key1'));
    }

    public function testGetMultiple_throwsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('getMultiple is not implemented yet.');

        $this->cache(new FakeRedisClient())->getMultiple(['key1']);
    }

    public function testSetMultiple_throwsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('setMultiple is not implemented yet.');

        $this->cache(new FakeRedisClient())->setMultiple(['key1' => 'value1']);
    }

    public function testDeleteMultiple_throwsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('deleteMultiple is not implemented yet.');

        $this->cache(new FakeRedisClient())->deleteMultiple(['key1']);
    }

    private function cache(FakeRedisClient $client, ?FakeClock $clock = null): RedisCache
    {
        $builder = RedisCache::builder()->client($client);
        if ($clock !== null) {
            $builder->clock($clock);
        }
        return $builder->build();
    }

    private function payload(FakeRedisClient $client, string $key): array
    {
        $decoded = json_decode($client->items[$key], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        return $decoded;
    }
}
