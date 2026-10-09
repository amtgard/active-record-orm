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
class ApcuCache implements CacheInterface
{
    use Builder;

    private ApcuStore $store;

    private Clock $clock;

    private LoggerInterface $logger;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->store = Optional::ofNullable(isset($this->store) ? $this->store : null)
            ->orElseGet(fn () => NativeApcuStore::builder()->build());
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
            $this->store->delete($key);
            return true;
        }
        $expiresAt = $this->expiresAt($ttl);
        return $this->store->store($key, [
            'value' => $value,
            'expiresAt' => $expiresAt,
        ], $this->apcuTtl($ttl, $expiresAt));
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return $this->store->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return $this->store->clear();
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
            $stored = $this->store->fetch($key);
        } catch (\Throwable) {
            return $this->failedRead($key);
        }
        if ($stored === false) {
            return $this->failedRead($key);
        }
        if (!$this->readable($stored)) {
            return ['present' => false, 'value' => null];
        }
        if ($this->timestampHasPassed($stored['expiresAt'])) {
            $this->store->delete($key);
            return ['present' => false, 'value' => null];
        }
        return ['present' => true, 'value' => $stored['value']];
    }

    /**
     * @return array{present: bool, value: mixed}
     */
    private function failedRead(string $key): array
    {
        $this->logger->info('APCu get failure treated as a miss', ['key' => $key]);
        return ['present' => false, 'value' => null];
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

    private function apcuTtl(\DateInterval|int|null $ttl, ?int $expiresAt): int
    {
        if ($ttl === null) {
            return 0;
        }
        if (is_int($ttl)) {
            return $ttl;
        }
        return $expiresAt - $this->clock->now();
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
