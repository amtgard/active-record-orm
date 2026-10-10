<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\SystemClock;
use Amtgard\PHPUnit\AmtgardTestCase;

class SystemClockTest extends AmtgardTestCase
{
    public function testNow_returnsUnixSeconds(): void
    {
        $before = time();
        $now = SystemClock::builder()->build()->now();
        $after = time();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }
}
