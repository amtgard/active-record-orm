<?php

namespace Amtgard\ActiveRecordOrm\Cache;

/** Strategy */
interface ApcuStore
{
    public function fetch(string $key): mixed;

    public function store(string $key, mixed $value, int $ttl): bool;

    public function delete(string $key): bool;

    public function exists(string $key): bool;

    public function clear(): bool;
}
