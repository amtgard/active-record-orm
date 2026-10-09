<?php

namespace Amtgard\ActiveRecordOrm\Cache;

/** Strategy */
interface CacheLifetimeManagementPolicy
{
    public function expiry(CacheSubject $subject): \DateInterval|int|null;

    public function isExpired(CacheSubject $subject, CacheEnvelope $envelope): bool;

    public function onStore(CacheSubject $subject, CacheEnvelope $envelope): void;
}
