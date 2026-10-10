<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Schema\Schema;

/** Test double for asserting lazy miss-only suggestion behavior. */
class SpySchema extends Schema
{
    public int $suggestFieldCallCount = 0;

    public function suggestField(string $needle): string
    {
        $this->suggestFieldCallCount++;

        return parent::suggestField($needle);
    }
}
