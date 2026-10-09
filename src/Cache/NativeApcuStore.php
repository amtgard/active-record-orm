<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;

/** Adapter */
class NativeApcuStore implements ApcuStore
{
    use Builder;

    /** @var callable */
    private $fetch;

    /** @var callable */
    private $store;

    /** @var callable */
    private $delete;

    /** @var callable */
    private $exists;

    /** @var callable */
    private $clear;

    private function __construct(
        $fetch = 'apcu_fetch',
        $store = 'apcu_store',
        $delete = 'apcu_delete',
        $exists = 'apcu_exists',
        $clear = 'apcu_clear_cache',
    ) {
        $this->fetch = $fetch;
        $this->store = $store;
        $this->delete = $delete;
        $this->exists = $exists;
        $this->clear = $clear;
    }

    public function fetch(string $key): mixed
    {
        return ($this->fetch)($key);
    }

    public function store(string $key, mixed $value, int $ttl): bool
    {
        return ($this->store)($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return ($this->delete)($key);
    }

    public function exists(string $key): bool
    {
        return ($this->exists)($key);
    }

    public function clear(): bool
    {
        return ($this->clear)();
    }
}
