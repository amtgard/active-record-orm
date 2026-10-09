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
class InMemoryCache implements CacheInterface
{
    use Builder;

    /** @var array<string, array{value: mixed, expiresAt: int|null}> */
    private array $cache = [];

    private Clock $clock;

    private LoggerInterface $logger;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
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
            unset($this->cache[$key]);
            return true;
        }
        $this->cache[$key] = [
            'value' => $value,
            'expiresAt' => $this->expiresAt($ttl),
        ];
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
        if (!array_key_exists($key, $this->cache)) {
            return ['present' => false, 'value' => null];
        }
        $stored = $this->cache[$key];
        if ($this->timestampHasPassed($stored['expiresAt'])) {
            unset($this->cache[$key]);
            $this->logger->info('entry treated as expired', ['key' => $key]);
            return ['present' => false, 'value' => null];
        }
        return ['present' => true, 'value' => $stored['value']];
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
