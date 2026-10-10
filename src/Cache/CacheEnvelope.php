<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;

/** Value Object */
class CacheEnvelope
{
    use Builder;

    private string $payload;
    private ?int $expiresAt = null;

    private function __construct()
    {
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }
}
