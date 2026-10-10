<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\Clock;

class FakeClock implements Clock
{
    private int $now;

    public function __construct(int $now)
    {
        $this->now = $now;
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
