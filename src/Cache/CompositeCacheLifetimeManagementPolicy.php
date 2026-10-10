<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** Composite */
class CompositeCacheLifetimeManagementPolicy implements CacheLifetimeManagementPolicy
{
    use Builder;

    /** @var CacheLifetimeManagementPolicy[] */
    private array $policies = [];

    private LoggerInterface $logger;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->logger = Optional::ofNullable(isset($this->logger) ? $this->logger : null)
            ->orElseGet(fn () => new NullLogger());
    }

    public function expiry(CacheSubject $subject): \DateInterval|int|null
    {
        $chosen = null;
        $chosenSeconds = null;
        foreach ($this->policies as $policy) {
            $ttl = $policy->expiry($subject);
            $seconds = $this->ttlSeconds($ttl);
            if ($seconds === null) {
                continue;
            }
            if ($chosenSeconds === null || $seconds < $chosenSeconds) {
                $chosen = $ttl;
                $chosenSeconds = $seconds;
            }
        }
        $this->logger->debug('expiry chosen', [
            'database' => $subject->getDatabaseName(),
            'entryKind' => $subject->getEntryKind(),
            'keyId' => $subject->getKeyId(),
            'ttl' => $chosenSeconds,
        ]);
        return $chosen;
    }

    public function isExpired(CacheSubject $subject, CacheEnvelope $envelope): bool
    {
        foreach ($this->policies as $policy) {
            if ($policy->isExpired($subject, $envelope)) {
                $this->logger->info('entry treated as expired', [
                    'database' => $subject->getDatabaseName(),
                    'entryKind' => $subject->getEntryKind(),
                    'keyId' => $subject->getKeyId(),
                ]);
                return true;
            }
        }
        return false;
    }

    public function onStore(CacheSubject $subject, CacheEnvelope $envelope): void
    {
        foreach ($this->policies as $policy) {
            $policy->onStore($subject, $envelope);
        }
    }

    private function ttlSeconds(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if (is_int($ttl)) {
            return $ttl;
        }
        $start = new \DateTimeImmutable('@0');
        return $start->add(clone $ttl)->getTimestamp();
    }
}
