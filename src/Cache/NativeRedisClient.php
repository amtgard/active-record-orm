<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;

/** Adapter */
class NativeRedisClient implements RedisClient
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

    private ?object $redis;

    /** @var callable|null */
    private $redisFactory;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->get = Optional::ofNullable(isset($this->get) ? $this->get : null)
            ->orElse($this->readFromRedis(...));
        $this->set = Optional::ofNullable(isset($this->set) ? $this->set : null)
            ->orElse($this->writeToRedis(...));
        $this->delete = Optional::ofNullable(isset($this->delete) ? $this->delete : null)
            ->orElse($this->deleteFromRedis(...));
        $this->clear = Optional::ofNullable(isset($this->clear) ? $this->clear : null)
            ->orElse($this->clearRedis(...));
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

    private function readFromRedis(string $key): mixed
    {
        return $this->redis()->get($key);
    }

    private function writeToRedis(string $key, string $value, ?int $ttl): bool
    {
        $redis = $this->redis();
        $result = $ttl === null
            ? $redis->set($key, $value)
            : $redis->set($key, $value, ['EX' => $ttl]);
        return $result === true;
    }

    private function deleteFromRedis(string $key): bool
    {
        return $this->redis()->del($key) !== false;
    }

    private function clearRedis(): bool
    {
        return $this->redis()->flushDB() !== false;
    }

    private function redis(): object
    {
        if (isset($this->redis)) {
            return $this->redis;
        }
        $this->redis = $this->open($this->host(), $this->port());
        return $this->redis;
    }

    private function open(string $host, int $port): object
    {
        $redis = $this->newRedis();
        if ($redis->connect($host, $port) !== true) {
            throw new \RuntimeException('Redis connection failed');
        }
        return $redis;
    }

    private function newRedis(): object
    {
        $factory = Optional::ofNullable(isset($this->redisFactory) ? $this->redisFactory : null)
            ->orElse($this->createRedis(...));
        return $factory();
    }

    private function createRedis(): \Redis
    {
        return new \Redis();
    }

    private function host(): string
    {
        return Optional::ofNullable($this->envString('REDIS_HOST'))
            ->orElse('127.0.0.1');
    }

    private function port(): int
    {
        return Optional::ofNullable($this->envString('REDIS_PORT'))
            ->map(fn (string $value): ?int => $this->portNumber($value))
            ->orElse(6379);
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
