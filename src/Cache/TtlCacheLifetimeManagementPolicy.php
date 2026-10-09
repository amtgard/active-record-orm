<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** Strategy */
class TtlCacheLifetimeManagementPolicy implements CacheLifetimeManagementPolicy
{
    use Builder;

    private Clock $clock;

    private LoggerInterface $logger;

    private \DateInterval|int|null $ttl = 43200;

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

    public function expiry(CacheSubject $subject): \DateInterval|int|null
    {
        $this->logger->debug('expiry chosen', [
            'database' => $subject->getDatabaseName(),
            'entryKind' => $subject->getEntryKind(),
            'keyId' => $subject->getKeyId(),
            'ttl' => $this->ttl,
        ]);
        return $this->ttl;
    }

    public function isExpired(CacheSubject $subject, CacheEnvelope $envelope): bool
    {
        return Optional::ofNullable($envelope->getExpiresAt())
            ->map(function (int $expiresAt) use ($subject) {
                $expired = $expiresAt <= $this->clock->now();
                if ($expired) {
                    $this->logger->info('entry treated as expired', [
                        'database' => $subject->getDatabaseName(),
                        'entryKind' => $subject->getEntryKind(),
                        'keyId' => $subject->getKeyId(),
                    ]);
                }
                return $expired;
            })
            ->orElse(false);
    }

    public function onStore(CacheSubject $subject, CacheEnvelope $envelope): void
    {
    }
}
