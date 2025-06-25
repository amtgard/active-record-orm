<?php

namespace Tests\Unit\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\DeleteStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\DeleteExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class DeleteStatementTest extends AmtgardTestCase
{
    private DeleteStatement $deleteStatement;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->deleteStatement = new DeleteStatement();
    }

    public function testOrderedExpressionMap_returnsCorrectExpressionOrder(): void
    {
        // Set up reflection to access private properties
        $reflection = new \ReflectionClass($this->deleteStatement);
        
        $deleteExpressionProperty = $reflection->getProperty('deleteExpression');
        $deleteExpressionProperty->setAccessible(true);
        $mockDeleteExpression = Phake::mock(DeleteExpression::class);
        $deleteExpressionProperty->setValue($this->deleteStatement, $mockDeleteExpression);
        
        $whereExpressionProperty = $reflection->getProperty('whereExpression');
        $whereExpressionProperty->setAccessible(true);
        $mockWhereExpression = Phake::mock(WhereExpression::class);
        $whereExpressionProperty->setValue($this->deleteStatement, $mockWhereExpression);
        
        $result = $this->deleteStatement->orderedExpressionMap();
        
        // Verify the correct order: DELETE, WHERE
        self::assertCount(2, $result);
        self::assertSame($mockDeleteExpression, $result[0]);
        self::assertSame($mockWhereExpression, $result[1]);
    }
} 