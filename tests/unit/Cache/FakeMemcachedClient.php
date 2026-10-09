<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\MemcachedClient;

class FakeMemcachedClient implements MemcachedClient
{
    /** @var array<string, string> */
    public array $items = [];

    /** @var array<string, int|null> */
    public array $ttls = [];

    public bool $getReturnsFalse = false;

    public bool $getThrows = false;

    public bool $setReturnsFalse = false;

    public bool $setThrows = false;

    public bool $deleteReturnsFalse = false;

    public bool $clearReturnsFalse = false;

    public int $setCalls = 0;

    public function get(string $key): mixed
    {
        if ($this->getThrows) {
            throw new \RuntimeException('memcached get failed');
        }
        if ($this->getReturnsFalse) {
            return false;
        }
        if (!array_key_exists($key, $this->items)) {
            return false;
        }
        return $this->items[$key];
    }

    public function set(string $key, string $value, ?int $ttl): bool
    {
        $this->setCalls++;
        if ($this->setThrows) {
            throw new \RuntimeException('memcached set failed');
        }
        if ($this->setReturnsFalse) {
            return false;
        }
        $this->items[$key] = $value;
        $this->ttls[$key] = $ttl;
        return true;
    }

    public function delete(string $key): bool
    {
        if ($this->deleteReturnsFalse) {
            return false;
        }
        unset($this->items[$key], $this->ttls[$key]);
        return true;
    }

    public function clear(): bool
    {
        if ($this->clearReturnsFalse) {
            return false;
        }
        $this->items = [];
        $this->ttls = [];
        return true;
    }
}
