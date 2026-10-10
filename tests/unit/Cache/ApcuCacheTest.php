<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\ApcuCache;
use Amtgard\ActiveRecordOrm\Cache\NativeApcuStore;
use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\PHPUnit\AmtgardTestCase;

class ApcuCacheTest extends AmtgardTestCase
{
    public function testBuild_withoutStore_usesNativeApcuStore(): void
    {
        $cache = ApcuCache::builder()->build();

        self::assertInstanceOf(
            NativeApcuStore::class,
            (new \ReflectionProperty(ApcuCache::class, 'store'))->getValue($cache)
        );
    }

    public function testBuild_withoutClockOrLogger_storesAndReads(): void
    {
        $store = new FakeApcuStore();
        $cache = ApcuCache::builder()->store($store)->build();

        self::assertTrue($cache->set('schema', 'document', null));
        self::assertSame(0, $store->ttls['schema']);
        self::assertSame('document', $cache->get('schema'));
        self::assertTrue($cache->has('schema'));
    }

    public function testGet_withNonExistingKey_returnsDefault(): void
    {
        $cache = $this->cache(new FakeApcuStore());

        self::assertSame('default_value', $cache->get('missing', 'default_value'));
        self::assertFalse($cache->has('missing'));
    }

    public function testGet_whenFetchReturnsFalse_returnsDefault(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('schema', 'document', null);
        $store->fetchReturnsFalse = true;

        self::assertSame('default', $cache->get('schema', 'default'));
        self::assertFalse($cache->has('schema'));
    }

    public function testGet_whenFetchThrows_returnsDefault(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('schema', 'document', null);
        $store->fetchThrows = true;

        self::assertSame('default', $cache->get('schema', 'default'));
        self::assertFalse($cache->has('schema'));
    }

    public function testGet_withUnreadablePayload_returnsDefault(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $store->items['text'] = 'document';
        $store->items['missing-value'] = ['expiresAt' => null];
        $store->items['missing-expiry'] = ['value' => 'document'];
        $store->items['bad-expiry'] = ['value' => 'document', 'expiresAt' => 'soon'];

        self::assertSame('default', $cache->get('text', 'default'));
        self::assertSame('default', $cache->get('missing-value', 'default'));
        self::assertSame('default', $cache->get('missing-expiry', 'default'));
        self::assertSame('default', $cache->get('bad-expiry', 'default'));
        self::assertFalse($cache->has('text'));
    }

    public function testSet_withNewKey_storesEnvelopeAndApcuTtl(): void
    {
        $clock = new FakeClock(5_000);
        $store = new FakeApcuStore();
        $cache = $this->cache($store, $clock);
        $key = 'amtgard_orm:ork:schema:users';

        self::assertTrue($cache->set($key, 'document', 30));
        self::assertTrue($cache->has($key));
        self::assertSame('document', $cache->get($key));
        self::assertSame(['value' => 'document', 'expiresAt' => 5_030], $store->items[$key]);
        self::assertSame(30, $store->ttls[$key]);
    }

    public function testSet_withExistingKey_replacesValue(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('existing_key', 'initial_value', null);

        self::assertTrue($cache->set('existing_key', 'new_value', 15));
        self::assertSame('new_value', $cache->get('existing_key'));
        self::assertSame(15, $store->ttls['existing_key']);
    }

    public function testSet_isVisibleToAnotherAdapterOnTheSameStore(): void
    {
        $clock = new FakeClock(100);
        $store = new FakeApcuStore();
        $first = $this->cache($store, $clock);
        $second = $this->cache($store, $clock);
        $first->set('shared', 'value', null);

        self::assertSame('value', $second->get('shared'));
        self::assertTrue($second->has('shared'));
        self::assertSame(0, $store->ttls['shared']);
    }

    public function testSet_withNullTtl_doesNotExpireAndPassesApcuTtlZero(): void
    {
        $clock = new FakeClock(1_000);
        $store = new FakeApcuStore();
        $cache = $this->cache($store, $clock);

        self::assertTrue($cache->set('durable', 'value', null));
        $clock->advance(100_000);

        self::assertTrue($cache->has('durable'));
        self::assertSame('value', $cache->get('durable'));
        self::assertSame(0, $store->ttls['durable']);
        self::assertNull($store->items['durable']['expiresAt']);
    }

    public function testSet_withZeroTtl_deletesKey(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('gone', 'value', null);
        $calls = $store->storeCalls;

        self::assertTrue($cache->set('gone', 'replacement', 0));
        self::assertSame($calls, $store->storeCalls);
        self::assertArrayNotHasKey('gone', $store->items);
        self::assertFalse($cache->has('gone'));
        self::assertSame('default', $cache->get('gone', 'default'));
    }

    public function testSet_withNegativeTtl_deletesKey(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('gone', 'value', null);

        self::assertTrue($cache->set('gone', 'replacement', -1));
        self::assertArrayNotHasKey('gone', $store->items);
        self::assertFalse($cache->has('gone'));
        self::assertNull($cache->get('gone'));
    }

    public function testSet_withNegativeTtlOnMissingKey_leavesKeyAbsent(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);

        self::assertTrue($cache->set('missing', 'value', -5));
        self::assertSame(0, $store->storeCalls);
        self::assertArrayNotHasKey('missing', $store->items);
        self::assertFalse($cache->has('missing'));
    }

    public function testSet_withPositiveIntTtl_expiresAgainstClock(): void
    {
        $clock = new FakeClock(5_000);
        $store = new FakeApcuStore();
        $cache = $this->cache($store, $clock);

        self::assertTrue($cache->set('temp', 'value', 10));
        self::assertSame(10, $store->ttls['temp']);
        self::assertTrue($cache->has('temp'));

        $clock->advance(9);
        self::assertTrue($cache->has('temp'));
        self::assertSame('value', $cache->get('temp', 'default'));

        $clock->advance(1);
        self::assertFalse($cache->has('temp'));
        self::assertSame('default', $cache->get('temp', 'default'));
        self::assertArrayNotHasKey('temp', $store->items);
    }

    public function testSet_withDateIntervalTtl_expiresAgainstClockAndPassesPositiveApcuTtl(): void
    {
        $clock = new FakeClock(8_000);
        $store = new FakeApcuStore();
        $cache = $this->cache($store, $clock);
        $ttl = new \DateInterval('PT1H30M');

        self::assertTrue($cache->set('interval', 'value', $ttl));
        self::assertTrue($cache->set('again', 'other', $ttl));
        self::assertSame(5400, $store->ttls['interval']);
        self::assertSame(13_400, $store->items['interval']['expiresAt']);

        $clock->advance(5399);
        self::assertTrue($cache->has('interval'));
        self::assertSame('other', $cache->get('again'));

        $clock->advance(1);
        self::assertFalse($cache->has('interval'));
        self::assertSame('default', $cache->get('again', 'default'));
        self::assertArrayNotHasKey('interval', $store->items);
    }

    public function testSet_withZeroDateInterval_deletesKey(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('zero', 'value', null);
        $calls = $store->storeCalls;

        self::assertTrue($cache->set('zero', 'replacement', new \DateInterval('PT0S')));
        self::assertSame($calls, $store->storeCalls);
        self::assertArrayNotHasKey('zero', $store->items);
        self::assertFalse($cache->has('zero'));
        self::assertSame('default', $cache->get('zero', 'default'));
    }

    public function testSet_withNegativeDateInterval_deletesKey(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('negative', 'value', null);
        $ttl = new \DateInterval('PT5S');
        $ttl->invert = 1;

        self::assertTrue($cache->set('negative', 'replacement', $ttl));
        self::assertArrayNotHasKey('negative', $store->items);
        self::assertFalse($cache->has('negative'));
    }

    public function testSet_withDayDateInterval_remainsUntilThatDayPasses(): void
    {
        $clock = new FakeClock(10_000);
        $store = new FakeApcuStore();
        $cache = $this->cache($store, $clock);

        self::assertTrue($cache->set('day', 'value', new \DateInterval('P1D')));
        self::assertSame(86400, $store->ttls['day']);
        $clock->advance(86399);
        self::assertTrue($cache->has('day'));

        $clock->advance(1);
        self::assertFalse($cache->has('day'));
    }

    public function testSet_afterExpiry_storesAFreshValue(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cache(new FakeApcuStore(), $clock);
        $cache->set('refill', 'old', 1);
        $clock->advance(1);
        $cache->set('refill', 'new', null);

        self::assertSame('new', $cache->get('refill', 'default'));
    }

    public function testSet_replacesTtlOfExistingKey(): void
    {
        $clock = new FakeClock(100);
        $store = new FakeApcuStore();
        $cache = $this->cache($store, $clock);
        $cache->set('replace', 'value', 5);
        $cache->set('replace', 'later', null);
        $clock->advance(50);

        self::assertSame('later', $cache->get('replace'));
        self::assertSame(0, $store->ttls['replace']);
        self::assertNull($store->items['replace']['expiresAt']);
    }

    public function testSet_storesNullFalseAndZero(): void
    {
        $cache = $this->cache(new FakeApcuStore());

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

    public function testSet_whenStoreFails_returnsFalse(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('schema', 'document', null);
        $store->storeReturnsFalse = true;

        self::assertFalse($cache->set('schema', 'next', 10));
        self::assertSame('document', $cache->get('schema'));
        self::assertSame(0, $store->ttls['schema']);
    }

    public function testDelete_withExistingKey_removesValue(): void
    {
        $cache = $this->cache(new FakeApcuStore());
        $cache->set('delete_key', 'delete_value', null);

        self::assertTrue($cache->delete('delete_key'));
        self::assertFalse($cache->has('delete_key'));
    }

    public function testDelete_withNonExistingKey_returnsTrue(): void
    {
        $cache = $this->cache(new FakeApcuStore());

        self::assertTrue($cache->delete('missing'));
    }

    public function testDelete_whenStoreFails_returnsFalse(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('schema', 'document', null);
        $store->deleteReturnsFalse = true;

        self::assertFalse($cache->delete('schema'));
        self::assertSame('document', $cache->get('schema'));
    }

    public function testClear_removesAllValues(): void
    {
        $cache = $this->cache(new FakeApcuStore());
        $cache->set('key1', 'value1', null);
        $cache->set('key2', 'value2', null);

        self::assertTrue($cache->clear());
        self::assertFalse($cache->has('key1'));
        self::assertFalse($cache->has('key2'));
    }

    public function testClear_whenStoreFails_returnsFalse(): void
    {
        $store = new FakeApcuStore();
        $cache = $this->cache($store);
        $cache->set('key1', 'value1', null);
        $store->clearReturnsFalse = true;

        self::assertFalse($cache->clear());
        self::assertSame('value1', $cache->get('key1'));
    }

    public function testGetMultiple_throwsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('getMultiple is not implemented yet.');

        $this->cache(new FakeApcuStore())->getMultiple(['key1']);
    }

    public function testSetMultiple_throwsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('setMultiple is not implemented yet.');

        $this->cache(new FakeApcuStore())->setMultiple(['key1' => 'value1']);
    }

    public function testDeleteMultiple_throwsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('deleteMultiple is not implemented yet.');

        $this->cache(new FakeApcuStore())->deleteMultiple(['key1']);
    }

    private function cache(FakeApcuStore $store, ?FakeClock $clock = null): ApcuCache
    {
        $builder = ApcuCache::builder()->store($store);
        if ($clock !== null) {
            $builder->clock($clock);
        }
        return $builder->build();
    }
}
