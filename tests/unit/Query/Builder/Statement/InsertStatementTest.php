<?php

namespace Tests\Unit\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\InsertStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\InsertExpression;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class InsertStatementTest extends AmtgardTestCase
{
    private InsertStatement $insertStatement;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->insertStatement = new InsertStatement();
    }

    public function testOrderedExpressionMap_returnsCorrectExpressionOrder(): void
    {
        // Set up reflection to access private properties
        $reflection = new \ReflectionClass($this->insertStatement);
        
        $insertExpressionProperty = $reflection->getProperty('insertExpression');
        $insertExpressionProperty->setAccessible(true);
        $mockInsertExpression = Phake::mock(InsertExpression::class);
        $insertExpressionProperty->setValue($this->insertStatement, $mockInsertExpression);
        
        $result = $this->insertStatement->orderedExpressionMap();
        
        // Verify the correct order: INSERT
        self::assertCount(1, $result);
        self::assertSame($mockInsertExpression, $result[0]);
    }
} 