<?php

namespace Tests\Unit\Trait;

use Amtgard\ActiveRecordOrm\Trait\EntityMapperTrait;
use Amtgard\PHPUnit\AmtgardTestCase;

class EntityMapperTraitTest extends AmtgardTestCase
{
    public function testTraitCanBeUsed(): void
    {
        $testClass = new class {
            use EntityMapperTrait;
        };

        self::assertTrue(trait_exists(EntityMapperTrait::class));
        self::assertTrue(in_array(EntityMapperTrait::class, class_uses($testClass)));
    }
}
