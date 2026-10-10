<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\CacheEnvelope;
use Amtgard\ActiveRecordOrm\Cache\CacheSubject;
use Amtgard\ActiveRecordOrm\Cache\TtlCacheLifetimeManagementPolicy;
use Amtgard\PHPUnit\AmtgardTestCase;

class TtlCacheLifetimeManagementPolicyTest extends AmtgardTestCase
{
    public function testExpiry_defaultsToTwelveHours(): void
    {
        $policy = TtlCacheLifetimeManagementPolicy::builder()->build();

        self::assertSame(43200, $policy->expiry($this->subject()));
    }

    public function testExpiry_returnsConfiguredIntTtl(): void
    {
        $policy = TtlCacheLifetimeManagementPolicy::builder()
            ->ttl(90)
            ->build();

        self::assertSame(90, $policy->expiry($this->subject()));
    }

    public function testExpiry_returnsConfiguredDateInterval(): void
    {
        $ttl = new \DateInterval('PT15M');
        $policy = TtlCacheLifetimeManagementPolicy::builder()
            ->ttl($ttl)
            ->build();

        self::assertSame($ttl, $policy->expiry($this->subject()));
    }

    public function testExpiry_returnsNullWhenConfiguredWithoutExpiry(): void
    {
        $policy = TtlCacheLifetimeManagementPolicy::builder()
            ->ttl(null)
            ->build();

        self::assertNull($policy->expiry($this->subject()));
    }

    public function testIsExpired_nullEnvelopeExpiryIsNotExpired(): void
    {
        $policy = TtlCacheLifetimeManagementPolicy::builder()
            ->clock(new FakeClock(5_000))
            ->ttl(60)
            ->build();

        self::assertFalse($policy->isExpired($this->subject(), $this->envelope('payload', null)));
    }

    public function testIsExpired_comparesEnvelopeExpiryToClock(): void
    {
        $policy = TtlCacheLifetimeManagementPolicy::builder()
            ->clock(new FakeClock(5_000))
            ->ttl(60)
            ->build();
        $subject = $this->subject();

        self::assertFalse($policy->isExpired($subject, $this->envelope('later', 5_001)));
        self::assertTrue($policy->isExpired($subject, $this->envelope('now', 5_000)));
        self::assertTrue($policy->isExpired($subject, $this->envelope('past', 4_999)));
        self::assertTrue($policy->isExpired($subject, $this->envelope('epoch', 0)));
    }

    public function testIsExpired_usesSystemClockWhenNoneIsInjected(): void
    {
        $policy = TtlCacheLifetimeManagementPolicy::builder()->build();
        $subject = $this->subject();

        self::assertTrue($policy->isExpired($subject, $this->envelope('past', 0)));
        self::assertFalse($policy->isExpired($subject, $this->envelope('future', time() + 86_400)));
    }

    public function testOnStore_doesNothing(): void
    {
        $policy = TtlCacheLifetimeManagementPolicy::builder()
            ->ttl(60)
            ->build();
        $subject = $this->subject();
        $envelope = $this->envelope('payload', 10);

        $policy->onStore($subject, $envelope);

        self::assertSame(60, $policy->expiry($subject));
        self::assertSame('payload', $envelope->getPayload());
        self::assertSame(10, $envelope->getExpiresAt());
    }

    private function subject(): CacheSubject
    {
        return CacheSubject::builder()
            ->databaseName('ork')
            ->entryKind('schema')
            ->keyId('users')
            ->build();
    }

    private function envelope(string $payload, ?int $expiresAt): CacheEnvelope
    {
        return CacheEnvelope::builder()
            ->payload($payload)
            ->expiresAt($expiresAt)
            ->build();
    }
}
