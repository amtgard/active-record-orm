<?php

namespace Tests\Unit\Query;

use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\PHPUnit\AmtgardTestCase;

class OperationTest extends AmtgardTestCase
{
    public function testFromString_withValidEnumValues_returnsCorrectOperation(): void
    {
        self::assertEquals(Operation::Equals, Operation::fromString('equals'));
        self::assertEquals(Operation::Set, Operation::fromString('set'));
        self::assertEquals(Operation::Like, Operation::fromString('like'));
        self::assertEquals(Operation::NotLike, Operation::fromString('notLike'));
        self::assertEquals(Operation::Greater, Operation::fromString('gt'));
        self::assertEquals(Operation::Less, Operation::fromString('lt'));
        self::assertEquals(Operation::GreaterOrEqual, Operation::fromString('gte'));
        self::assertEquals(Operation::LessOrEqual, Operation::fromString('lte'));
        self::assertEquals(Operation::Contains, Operation::fromString('contains'));
        self::assertEquals(Operation::StartsWith, Operation::fromString('startsWith'));
        self::assertEquals(Operation::EndsWith, Operation::fromString('endsWith'));
        self::assertEquals(Operation::In, Operation::fromString('in'));
        self::assertEquals(Operation::NotIn, Operation::fromString('notIn'));
        self::assertEquals(Operation::Between, Operation::fromString('between'));
        self::assertEquals(Operation::NotBetween, Operation::fromString('notBetween'));
        self::assertEquals(Operation::IsNull, Operation::fromString('isNull'));
        self::assertEquals(Operation::IsNotNull, Operation::fromString('isNotNull'));
        self::assertEquals(Operation::And, Operation::fromString('and'));
        self::assertEquals(Operation::Or, Operation::fromString('or'));
    }

    public function testFromString_withAliasValues_returnsCorrectOperation(): void
    {
        self::assertEquals(Operation::Greater, Operation::fromString('greater'));
        self::assertEquals(Operation::Greater, Operation::fromString('greaterThan'));
        self::assertEquals(Operation::GreaterOrEqual, Operation::fromString('greaterThanOrEqualTo'));
        self::assertEquals(Operation::Less, Operation::fromString('less'));
        self::assertEquals(Operation::Less, Operation::fromString('lessThan'));
        self::assertEquals(Operation::LessOrEqual, Operation::fromString('lessThanOrEqualTo'));
    }

    public function testFromString_withInvalidValue_throwsTypeError(): void
    {
        self::assertThrows(\TypeError::class, fn() => Operation::fromString('invalid_operation'));
        self::assertThrows(\TypeError::class, fn() => Operation::fromString(''));
        self::assertThrows(\TypeError::class, fn() => Operation::fromString('random_string'));
    }

    public function testEnumValues_haveCorrectStringValues(): void
    {
        self::assertEquals('equals', Operation::Equals->value);
        self::assertEquals('set', Operation::Set->value);
        self::assertEquals('like', Operation::Like->value);
        self::assertEquals('notLike', Operation::NotLike->value);
        self::assertEquals('gt', Operation::Greater->value);
        self::assertEquals('lt', Operation::Less->value);
        self::assertEquals('gte', Operation::GreaterOrEqual->value);
        self::assertEquals('lte', Operation::LessOrEqual->value);
        self::assertEquals('contains', Operation::Contains->value);
        self::assertEquals('startsWith', Operation::StartsWith->value);
        self::assertEquals('endsWith', Operation::EndsWith->value);
        self::assertEquals('in', Operation::In->value);
        self::assertEquals('notIn', Operation::NotIn->value);
        self::assertEquals('between', Operation::Between->value);
        self::assertEquals('notBetween', Operation::NotBetween->value);
        self::assertEquals('isNull', Operation::IsNull->value);
        self::assertEquals('isNotNull', Operation::IsNotNull->value);
        self::assertEquals('and', Operation::And->value);
        self::assertEquals('or', Operation::Or->value);
    }

    public function testEnumCases_areUnique(): void
    {
        $values = [];
        foreach (Operation::cases() as $case) {
            self::assertNotContains($case->value, $values, "Duplicate value found: {$case->value}");
            $values[] = $case->value;
        }
    }
} 