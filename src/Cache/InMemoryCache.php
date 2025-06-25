<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\Traits\Builder\Builder;
use Psr\SimpleCache\CacheInterface;

class InMemoryCache implements CacheInterface
{
    use Builder;

    private array $cache = [];
    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->cache[$key] : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        if (isset($this->cache[$key])) {
            throw new NotImplementedException("ttl is not implemented for set() yet.");
        }
        $this->cache[$key] = $value;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        throw new NotImplementedException("getMultiple is not implemented yet.");
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        throw new NotImplementedException("setMultiple is not implemented yet.");
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        throw new NotImplementedException("deleteMultiple is not implemented yet.");
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }
}