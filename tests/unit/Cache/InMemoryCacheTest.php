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

    public function testSet_withExistingKey_throwsNotImplementedException(): void
    {
        $key = 'existing_key';
        $value = 'initial_value';
        
        $this->cache->set($key, $value);
        
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('ttl is not implemented for set() yet.');
        
        $this->cache->set($key, 'new_value');
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