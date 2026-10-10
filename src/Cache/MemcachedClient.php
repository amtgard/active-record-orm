<?php

namespace Amtgard\ActiveRecordOrm\Cache;

/** Strategy */
interface MemcachedClient
{
    public function get(string $key): mixed;

    public function set(string $key, string $value, ?int $ttl): bool;

    public function delete(string $key): bool;

    public function clear(): bool;
}
