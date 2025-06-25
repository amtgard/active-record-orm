<?php

namespace Tests\Unit\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\SelectStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\SelectExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\FromExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\OrderByExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\LimitExpression;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class SelectStatementTest extends AmtgardTestCase
{
    private SelectStatement $selectStatement;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->selectStatement = new SelectStatement();
    }

    public function testOrderedExpressionMap_returnsCorrectExpressionOrder(): void
    {
        // Set up reflection to access private properties
        $reflection = new \ReflectionClass($this->selectStatement);
        
        $selectExpressionProperty = $reflection->getProperty('selectExpression');
        $selectExpressionProperty->setAccessible(true);
        $mockSelectExpression = Phake::mock(SelectExpression::class);
        $selectExpressionProperty->setValue($this->selectStatement, $mockSelectExpression);
        
        $fromExpressionProperty = $reflection->getProperty('fromExpression');
        $fromExpressionProperty->setAccessible(true);
        $mockFromExpression = Phake::mock(FromExpression::class);
        $fromExpressionProperty->setValue($this->selectStatement, $mockFromExpression);
        
        $whereExpressionProperty = $reflection->getProperty('whereExpression');
        $whereExpressionProperty->setAccessible(true);
        $mockWhereExpression = Phake::mock(WhereExpression::class);
        $whereExpressionProperty->setValue($this->selectStatement, $mockWhereExpression);
        
        $orderByProperty = $reflection->getProperty('orderBy');
        $orderByProperty->setAccessible(true);
        $mockOrderByExpression = Phake::mock(OrderByExpression::class);
        $orderByProperty->setValue($this->selectStatement, $mockOrderByExpression);
        
        $limitProperty = $reflection->getProperty('limit');
        $limitProperty->setAccessible(true);
        $mockLimitExpression = Phake::mock(LimitExpression::class);
        $limitProperty->setValue($this->selectStatement, $mockLimitExpression);
        
        $result = $this->selectStatement->orderedExpressionMap();
        
        // Verify the correct order: SELECT, FROM, WHERE, ORDER BY, LIMIT
        self::assertCount(5, $result);
        self::assertSame($mockSelectExpression, $result[0]);
        self::assertSame($mockFromExpression, $result[1]);
        self::assertSame($mockWhereExpression, $result[2]);
        self::assertSame($mockOrderByExpression, $result[3]);
        self::assertSame($mockLimitExpression, $result[4]);
    }
} 