<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\ApcuStore;

class FakeApcuStore implements ApcuStore
{
    /** @var array<string, mixed> */
    public array $items = [];

    /** @var array<string, int> */
    public array $ttls = [];

    public bool $fetchReturnsFalse = false;

    public bool $fetchThrows = false;

    public bool $storeReturnsFalse = false;

    public bool $deleteReturnsFalse = false;

    public bool $clearReturnsFalse = false;

    public int $storeCalls = 0;

    public function fetch(string $key): mixed
    {
        if ($this->fetchThrows) {
            throw new \RuntimeException('apcu fetch failed');
        }
        if ($this->fetchReturnsFalse) {
            return false;
        }
        if (!array_key_exists($key, $this->items)) {
            return false;
        }
        return $this->items[$key];
    }

    public function store(string $key, mixed $value, int $ttl): bool
    {
        $this->storeCalls++;
        if ($this->storeReturnsFalse) {
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

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->items);
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
