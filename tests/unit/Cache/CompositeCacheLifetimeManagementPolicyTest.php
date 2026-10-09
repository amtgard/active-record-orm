<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\CacheEnvelope;
use Amtgard\ActiveRecordOrm\Cache\CacheLifetimeManagementPolicy;
use Amtgard\ActiveRecordOrm\Cache\CacheSubject;
use Amtgard\ActiveRecordOrm\Cache\CompositeCacheLifetimeManagementPolicy;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class CompositeCacheLifetimeManagementPolicyTest extends AmtgardTestCase
{
    public function testExpiry_withNoChildren_returnsNull(): void
    {
        $policy = CompositeCacheLifetimeManagementPolicy::builder()->build();

        self::assertNull($policy->expiry($this->subject()));
        self::assertFalse($policy->isExpired($this->subject(), $this->envelope()));
        $policy->onStore($this->subject(), $this->envelope());
        self::assertNull($policy->expiry($this->subject()));
    }

    public function testExpiry_skipsNullAndReturnsShortest(): void
    {
        $none = $this->policyReturning(null);
        $longer = $this->policyReturning(90);
        $shorter = $this->policyReturning(15);
        $policy = $this->composite($none, $longer, $shorter);

        self::assertSame(15, $policy->expiry($this->subject()));
    }

    public function testExpiry_zeroIsShorterThanAPositiveTtl(): void
    {
        $policy = $this->composite($this->policyReturning(10), $this->policyReturning(0));

        self::assertSame(0, $policy->expiry($this->subject()));
    }

    public function testExpiry_negativeIntIsShorterThanAPositiveTtl(): void
    {
        $policy = $this->composite($this->policyReturning(10), $this->policyReturning(-5));

        self::assertSame(-5, $policy->expiry($this->subject()));
    }

    public function testExpiry_prefersShorterDateIntervalOverLongerInt(): void
    {
        $hour = new \DateInterval('PT1H');
        $policy = $this->composite($this->policyReturning(7_200), $this->policyReturning($hour));

        self::assertSame($hour, $policy->expiry($this->subject()));
    }

    public function testExpiry_prefersShorterIntOverLongerDateInterval(): void
    {
        $hour = new \DateInterval('PT1H');
        $policy = $this->composite($this->policyReturning($hour), $this->policyReturning(100));

        self::assertSame(100, $policy->expiry($this->subject()));
    }

    public function testExpiry_keepsTheFirstTtlWhenDurationsMatch(): void
    {
        $hour = new \DateInterval('PT1H');
        $policy = $this->composite($this->policyReturning($hour), $this->policyReturning(3600));

        self::assertSame($hour, $policy->expiry($this->subject()));
    }

    public function testExpiry_prefersShorterDateInterval(): void
    {
        $twoHours = new \DateInterval('PT2H');
        $halfHour = new \DateInterval('PT30M');
        $policy = $this->composite($this->policyReturning($twoHours), $this->policyReturning($halfHour));

        self::assertSame($halfHour, $policy->expiry($this->subject()));
    }

    public function testExpiry_invertedDateIntervalIsShorterThanAPositiveInt(): void
    {
        $negativeHour = new \DateInterval('PT1H');
        $negativeHour->invert = 1;
        $policy = $this->composite($this->policyReturning(10), $this->policyReturning($negativeHour));

        self::assertSame($negativeHour, $policy->expiry($this->subject()));
    }

    public function testExpiry_allNullChildren_returnsNull(): void
    {
        $policy = $this->composite($this->policyReturning(null), $this->policyReturning(null));

        self::assertNull($policy->expiry($this->subject()));
    }

    public function testIsExpired_isTrueWhenAnyChildSaysSo(): void
    {
        $fresh = $this->expiryChild(false);
        $stale = $this->expiryChild(true);
        $unchecked = $this->expiryChild(true);
        $policy = $this->composite($fresh, $stale, $unchecked);
        $subject = $this->subject();
        $envelope = $this->envelope();

        self::assertTrue($policy->isExpired($subject, $envelope));
        Phake::verify($unchecked, Phake::never())->isExpired($subject, $envelope);
    }

    public function testIsExpired_isFalseWhenEveryChildSaysNo(): void
    {
        $policy = $this->composite($this->expiryChild(false), $this->expiryChild(false));

        self::assertFalse($policy->isExpired($this->subject(), $this->envelope()));
    }

    public function testOnStore_callsEveryChild(): void
    {
        $first = $this->policyReturning(10);
        $second = $this->policyReturning(20);
        $policy = $this->composite($first, $second);
        $subject = $this->subject();
        $envelope = $this->envelope();

        $policy->onStore($subject, $envelope);

        Phake::verify($first)->onStore($subject, $envelope);
        Phake::verify($second)->onStore($subject, $envelope);
    }

    private function composite(CacheLifetimeManagementPolicy ...$policies): CompositeCacheLifetimeManagementPolicy
    {
        return CompositeCacheLifetimeManagementPolicy::builder()
            ->policies($policies)
            ->build();
    }

    private function policyReturning(\DateInterval|int|null $ttl): CacheLifetimeManagementPolicy
    {
        $policy = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($policy)->expiry(Phake::anyParameters())->thenReturn($ttl);
        return $policy;
    }

    private function expiryChild(bool $expired): CacheLifetimeManagementPolicy
    {
        $policy = Phake::mock(CacheLifetimeManagementPolicy::class);
        Phake::when($policy)->isExpired(Phake::anyParameters())->thenReturn($expired);
        return $policy;
    }

    private function subject(): CacheSubject
    {
        return CacheSubject::builder()
            ->databaseName('ork')
            ->entryKind('schema')
            ->keyId('users')
            ->build();
    }

    private function envelope(): CacheEnvelope
    {
        return CacheEnvelope::builder()
            ->payload('document')
            ->expiresAt(50)
            ->build();
    }
}
