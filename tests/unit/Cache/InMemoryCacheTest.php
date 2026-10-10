<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\InMemoryCache;
use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\PHPUnit\AmtgardTestCase;
use Psr\SimpleCache\CacheInterface;

class InMemoryCacheTest extends AmtgardTestCase
{
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = InMemoryCache::builder()->build();
    }

    public function testGet_withExistingKey_returnsValue(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);
        
        self::assertEquals($value, $result);
    }

    public function testGet_withNonExistingKey_returnsDefault(): void
    {
        $key = 'non_existing_key';
        $default = 'default_value';
        
        $result = $this->cache->get($key, $default);
        
        self::assertEquals($default, $result);
    }

    public function testSet_withNewKey_storesValue(): void
    {
        $key = 'new_key';
        $value = 'new_value';
        
        $result = $this->cache->set($key, $value);
        
        self::assertTrue($result);
        self::assertTrue($this->cache->has($key));
        self::assertEquals($value, $this->cache->get($key));
    }

    public function testSet_withExistingKey_replacesValue(): void
    {
        $key = 'existing_key';

        $this->cache->set($key, 'initial_value');
        $result = $this->cache->set($key, 'new_value');

        self::assertTrue($result);
        self::assertSame('new_value', $this->cache->get($key));
    }

    public function testSet_withNullTtl_doesNotExpire(): void
    {
        $clock = new FakeClock(1_000);
        $cache = $this->cacheWithClock($clock);

        self::assertTrue($cache->set('durable', 'value', null));
        $clock->advance(100_000);

        self::assertTrue($cache->has('durable'));
        self::assertSame('value', $cache->get('durable'));
    }

    public function testSet_withZeroTtl_deletesKey(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));
        $cache->set('gone', 'value');

        self::assertTrue($cache->set('gone', 'replacement', 0));
        self::assertSame([], $this->storedEntries($cache));
        self::assertFalse($cache->has('gone'));
        self::assertSame('default', $cache->get('gone', 'default'));
    }

    public function testSet_withNegativeTtl_deletesKey(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));
        $cache->set('gone', 'value');

        self::assertTrue($cache->set('gone', 'replacement', -1));
        self::assertArrayNotHasKey('gone', $this->storedEntries($cache));
        self::assertFalse($cache->has('gone'));
        self::assertNull($cache->get('gone'));
    }

    public function testSet_withNegativeTtlOnMissingKey_leavesKeyAbsent(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(1_000));

        self::assertTrue($cache->set('missing', 'value', -5));
        self::assertArrayNotHasKey('missing', $this->storedEntries($cache));
        self::assertFalse($cache->has('missing'));
    }

    public function testSet_withPositiveIntTtl_expiresAgainstClock(): void
    {
        $clock = new FakeClock(5_000);
        $cache = $this->cacheWithClock($clock);

        self::assertTrue($cache->set('temp', 'value', 10));
        self::assertTrue($cache->has('temp'));

        $clock->advance(9);
        self::assertTrue($cache->has('temp'));
        self::assertSame('value', $cache->get('temp', 'default'));

        $clock->advance(1);
        self::assertFalse($cache->has('temp'));
        self::assertSame('default', $cache->get('temp', 'default'));
    }

    public function testSet_withDateIntervalTtl_expiresAgainstClock(): void
    {
        $clock = new FakeClock(8_000);
        $cache = $this->cacheWithClock($clock);
        $ttl = new \DateInterval('PT1H30M');

        self::assertTrue($cache->set('interval', 'value', $ttl));
        self::assertTrue($cache->set('again', 'other', $ttl));

        $clock->advance(5399);
        self::assertTrue($cache->has('interval'));
        self::assertSame('other', $cache->get('again'));

        $clock->advance(1);
        self::assertFalse($cache->has('interval'));
        self::assertSame('default', $cache->get('again', 'default'));
    }

    public function testSet_withZeroDateInterval_deletesKey(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(8_000));
        $cache->set('zero', 'value');

        self::assertTrue($cache->set('zero', 'replacement', new \DateInterval('PT0S')));
        self::assertArrayNotHasKey('zero', $this->storedEntries($cache));
        self::assertFalse($cache->has('zero'));
        self::assertSame('default', $cache->get('zero', 'default'));
    }

    public function testSet_withNegativeDateInterval_deletesKey(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(8_000));
        $cache->set('negative', 'value');
        $ttl = new \DateInterval('PT5S');
        $ttl->invert = 1;

        self::assertTrue($cache->set('negative', 'replacement', $ttl));
        self::assertArrayNotHasKey('negative', $this->storedEntries($cache));
        self::assertFalse($cache->has('negative'));
    }

    public function testSet_withDayDateInterval_remainsUntilThatDayPasses(): void
    {
        $clock = new FakeClock(10_000);
        $cache = $this->cacheWithClock($clock);

        self::assertTrue($cache->set('day', 'value', new \DateInterval('P1D')));
        $clock->advance(86399);
        self::assertTrue($cache->has('day'));

        $clock->advance(1);
        self::assertFalse($cache->has('day'));
    }

    public function testGet_withExpiredKey_returnsDefault(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cacheWithClock($clock);
        $cache->set('expired', 'value', 1);
        $clock->advance(1);

        self::assertSame('default', $cache->get('expired', 'default'));
        self::assertFalse($cache->has('expired'));
    }

    public function testSet_afterExpiry_storesAFreshValue(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cacheWithClock($clock);
        $cache->set('refill', 'old', 1);
        $clock->advance(1);
        $cache->set('refill', 'new', null);

        self::assertSame('new', $cache->get('refill', 'default'));
    }

    public function testSet_replacesTtlOfExistingKey(): void
    {
        $clock = new FakeClock(100);
        $cache = $this->cacheWithClock($clock);
        $cache->set('replace', 'value', 5);
        $cache->set('replace', 'later', null);
        $clock->advance(50);

        self::assertSame('later', $cache->get('replace'));
    }

    public function testSet_storesNullFalseAndZero(): void
    {
        $cache = $this->cacheWithClock(new FakeClock(100));

        $cache->set('null', null);
        $cache->set('false', false);
        $cache->set('zero', 0);
        $cache->set('empty', '');

        self::assertTrue($cache->has('null'));
        self::assertNull($cache->get('null', 'default'));
        self::assertTrue($cache->has('false'));
        self::assertFalse($cache->get('false', 'default'));
        self::assertSame(0, $cache->get('zero', 1));
        self::assertSame('', $cache->get('empty', 'default'));
    }

    public function testSet_withPositiveTtl_usesSystemClockWhenNoneIsInjected(): void
    {
        self::assertTrue($this->cache->set('system', 'value', 3600));
        self::assertTrue($this->cache->has('system'));
        self::assertSame('value', $this->cache->get('system'));
        self::assertTrue($this->cache->set('day', 'value', new \DateInterval('PT1H')));
        self::assertTrue($this->cache->has('day'));
    }

    private function cacheWithClock(FakeClock $clock): InMemoryCache
    {
        return InMemoryCache::builder()
            ->clock($clock)
            ->build();
    }

    private function storedEntries(InMemoryCache $cache): array
    {
        $entries = new \ReflectionProperty(InMemoryCache::class, 'cache');
        return $entries->getValue($cache);
    }

    public function testDelete_withExistingKey_removesValue(): void
    {
        $key = 'delete_key';
        $value = 'delete_value';
        
        $this->cache->set($key, $value);
        self::assertTrue($this->cache->has($key));
        
        $result = $this->cache->delete($key);
        
        self::assertTrue($result);
        self::assertFalse($this->cache->has($key));
    }

    public function testDelete_withNonExistingKey_returnsTrue(): void
    {
        $key = 'non_existing_delete_key';
        
        $result = $this->cache->delete($key);
        
        self::assertTrue($result);
    }

    public function testClear_removesAllValues(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        self::assertTrue($this->cache->has('key1'));
        self::assertTrue($this->cache->has('key2'));
        
        $result = $this->cache->clear();
        
        self::assertTrue($result);
        self::assertFalse($this->cache->has('key1'));
        self::assertFalse($this->cache->has('key2'));
    }

    public function testGetMultiple_throwsNotImplementedException(): void
    {
        $keys = ['key1', 'key2'];
        
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('getMultiple is not implemented yet.');
        
        $this->cache->getMultiple($keys);
    }

    public function testSetMultiple_throwsNotImplementedException(): void
    {
        $values = ['key1' => 'value1', 'key2' => 'value2'];
        
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('setMultiple is not implemented yet.');
        
        $this->cache->setMultiple($values);
    }

    public function testDeleteMultiple_throwsNotImplementedException(): void
    {
        $keys = ['key1', 'key2'];
        
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('deleteMultiple is not implemented yet.');
        
        $this->cache->deleteMultiple($keys);
    }

    public function testHas_withExistingKey_returnsTrue(): void
    {
        $key = 'has_key';
        $value = 'has_value';
        
        $this->cache->set($key, $value);
        $result = $this->cache->has($key);
        
        self::assertTrue($result);
    }

    public function testHas_withNonExistingKey_returnsFalse(): void
    {
        $key = 'non_existing_has_key';
        
        $result = $this->cache->has($key);
        
        self::assertFalse($result);
    }
} 