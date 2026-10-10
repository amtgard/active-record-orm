<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\CacheEnvelope;
use Amtgard\PHPUnit\AmtgardTestCase;

class CacheEnvelopeTest extends AmtgardTestCase
{
    public function testBuilder_assignsPayloadAndExpiry(): void
    {
        $envelope = CacheEnvelope::builder()
            ->payload('{"table":"users"}')
            ->expiresAt(1_700_000_000)
            ->build();

        self::assertSame('{"table":"users"}', $envelope->getPayload());
        self::assertSame(1_700_000_000, $envelope->getExpiresAt());
    }

    public function testBuilder_defaultsExpiryToNull(): void
    {
        $envelope = CacheEnvelope::builder()
            ->payload('document')
            ->build();

        self::assertSame('document', $envelope->getPayload());
        self::assertNull($envelope->getExpiresAt());
    }
}
