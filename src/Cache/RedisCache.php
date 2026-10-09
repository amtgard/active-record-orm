<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/** Adapter */
class RedisCache implements CacheInterface
{
    use Builder;

    private RedisClient $client;

    private Clock $clock;

    private LoggerInterface $logger;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->client = Optional::ofNullable(isset($this->client) ? $this->client : null)
            ->orElseGet(fn () => NativeRedisClient::builder()->build());
        $this->clock = Optional::ofNullable(isset($this->clock) ? $this->clock : null)
            ->orElseGet(fn () => SystemClock::builder()->build());
        $this->logger = Optional::ofNullable(isset($this->logger) ? $this->logger : null)
            ->orElseGet(fn () => new NullLogger());
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->liveEntry($key);
        return $entry['present'] ? $entry['value'] : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        if ($this->ttlDeletesImmediately($ttl)) {
            $this->client->delete($key);
            return true;
        }
        try {
            return $this->client->set($key, $this->encode($value, $this->expiresAt($ttl)), $this->redisTtl($ttl));
        } catch (\Throwable) {
            return $this->failedSet($key);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return $this->client->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return $this->client->clear();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        throw new NotImplementedException("getMultiple is not implemented yet.");
    }

    /**
     * @param mixed[] $values
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
        return $this->liveEntry($key)['present'];
    }

    /**
     * @return array{present: bool, value: mixed}
     */
    private function liveEntry(string $key): array
    {
        try {
            $stored = $this->client->get($key);
        } catch (\Throwable) {
            return $this->failedRead($key);
        }
        $entry = $this->decoded($stored);
        if ($entry === null) {
            return ['present' => false, 'value' => null];
        }
        if ($this->timestampHasPassed($entry['expiresAt'])) {
            $this->client->delete($key);
            return ['present' => false, 'value' => null];
        }
        return ['present' => true, 'value' => $entry['value']];
    }

    /**
     * @return array{present: bool, value: mixed}
     */
    private function failedRead(string $key): array
    {
        $this->logger->info('Redis get failure', ['key' => $key]);
        return ['present' => false, 'value' => null];
    }

    private function failedSet(string $key): bool
    {
        $this->logger->info('Redis set failure', ['key' => $key]);
        return false;
    }

    private function decoded(mixed $stored): ?array
    {
        if (!is_string($stored)) {
            return null;
        }
        try {
            $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!$this->readable($decoded)) {
            return null;
        }
        return $decoded;
    }

    private function readable(mixed $stored): bool
    {
        if (!is_array($stored)) {
            return false;
        }
        if (!array_key_exists('value', $stored) || !array_key_exists('expiresAt', $stored)) {
            return false;
        }
        return $stored['expiresAt'] === null || is_int($stored['expiresAt']);
    }

    private function encode(mixed $value, ?int $expiresAt): string
    {
        return json_encode(
            ['value' => $value, 'expiresAt' => $expiresAt],
            JSON_THROW_ON_ERROR
        );
    }

    private function redisTtl(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if (is_int($ttl)) {
            return $ttl;
        }
        return $this->expiresAt($ttl) - $this->clock->now();
    }

    private function ttlDeletesImmediately(\DateInterval|int|null $ttl): bool
    {
        if ($ttl === null) {
            return false;
        }
        if (is_int($ttl)) {
            return $ttl <= 0;
        }
        return $this->timestampHasPassed($this->expiresAt($ttl));
    }

    private function expiresAt(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if (is_int($ttl)) {
            return $this->clock->now() + $ttl;
        }
        return (new \DateTimeImmutable('@' . $this->clock->now()))
            ->add(clone $ttl)
            ->getTimestamp();
    }

    private function timestampHasPassed(?int $expiresAt): bool
    {
        return Optional::ofNullable($expiresAt)
            ->map(fn (int $expiresAt) => $expiresAt <= $this->clock->now())
            ->orElse(false);
    }
}
