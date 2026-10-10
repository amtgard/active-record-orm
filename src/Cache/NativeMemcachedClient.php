<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;

/** Adapter */
class NativeMemcachedClient implements MemcachedClient
{
    use Builder;

    /** @var callable */
    private $get;

    /** @var callable */
    private $set;

    /** @var callable */
    private $delete;

    /** @var callable */
    private $clear;

    private ?object $memcached;

    /** @var callable|null */
    private $memcachedFactory;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->get = Optional::ofNullable(isset($this->get) ? $this->get : null)
            ->orElse($this->readFromMemcached(...));
        $this->set = Optional::ofNullable(isset($this->set) ? $this->set : null)
            ->orElse($this->writeToMemcached(...));
        $this->delete = Optional::ofNullable(isset($this->delete) ? $this->delete : null)
            ->orElse($this->deleteFromMemcached(...));
        $this->clear = Optional::ofNullable(isset($this->clear) ? $this->clear : null)
            ->orElse($this->clearMemcached(...));
    }

    public function get(string $key): mixed
    {
        return ($this->get)($key);
    }

    public function set(string $key, string $value, ?int $ttl): bool
    {
        if ($ttl !== null && $ttl <= 0) {
            return $this->delete($key);
        }
        return ($this->set)($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return ($this->delete)($key);
    }

    public function clear(): bool
    {
        return ($this->clear)();
    }

    private function readFromMemcached(string $key): mixed
    {
        return $this->memcached()->get($key);
    }

    private function writeToMemcached(string $key, string $value, ?int $ttl): bool
    {
        $stored = $this->memcached()->set($key, $value, $this->expiration($ttl));
        return $stored === true;
    }

    private function deleteFromMemcached(string $key): bool
    {
        $memcached = $this->memcached();
        if ($memcached->delete($key) === true) {
            return true;
        }
        return $memcached->getResultCode() === 16;
    }

    private function clearMemcached(): bool
    {
        return $this->memcached()->flush() === true;
    }

    private function expiration(?int $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }
        if ($ttl > 2592000) {
            return time() + $ttl;
        }
        return $ttl;
    }

    private function memcached(): object
    {
        if (isset($this->memcached)) {
            return $this->memcached;
        }
        $this->memcached = $this->open($this->host(), $this->port());
        return $this->memcached;
    }

    private function open(string $host, int $port): object
    {
        $memcached = $this->newMemcached();
        if ($memcached->addServer($host, $port) !== true) {
            throw new \RuntimeException('Memcached connection failed');
        }
        return $memcached;
    }

    private function newMemcached(): object
    {
        $factory = Optional::ofNullable(isset($this->memcachedFactory) ? $this->memcachedFactory : null)
            ->orElse($this->createMemcached(...));
        return $factory();
    }

    private function createMemcached(): \Memcached
    {
        return new \Memcached();
    }

    private function host(): string
    {
        return Optional::ofNullable($this->envString('MEMCACHED_HOST'))
            ->orElse('127.0.0.1');
    }

    private function port(): int
    {
        return Optional::ofNullable($this->envString('MEMCACHED_PORT'))
            ->map(fn (string $value): ?int => $this->portNumber($value))
            ->orElse(11211);
    }

    private function portNumber(string $value): ?int
    {
        if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            return null;
        }
        return (int) $value;
    }

    private function envString(string $name): ?string
    {
        if (!array_key_exists($name, $_ENV)) {
            return null;
        }
        $value = $_ENV[$name];
        if (!is_string($value) || $value === '') {
            return null;
        }
        return $value;
    }
}
