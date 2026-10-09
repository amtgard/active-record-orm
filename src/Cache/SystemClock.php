<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;

/** Adapter */
class SystemClock implements Clock
{
    use Builder;

    private function __construct()
    {
    }

    public function now(): int
    {
        return time();
    }
}
