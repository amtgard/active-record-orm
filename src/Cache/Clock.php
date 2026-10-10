<?php

namespace Amtgard\ActiveRecordOrm\Cache;

/** Strategy */
interface Clock
{
    public function now(): int;
}
