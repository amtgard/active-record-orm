<?php

namespace Tests\Unit\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\UpdateStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\UpdateExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class UpdateStatementTest extends AmtgardTestCase
{
    private UpdateStatement $updateStatement;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->updateStatement = new UpdateStatement();
    }

    public function testOrderedExpressionMap_returnsCorrectExpressionOrder(): void
    {
        // Set up reflection to access private properties
        $reflection = new \ReflectionClass($this->updateStatement);
        
        $updateExpressionProperty = $reflection->getProperty('updateExpression');
        $updateExpressionProperty->setAccessible(true);
        $mockUpdateExpression = Phake::mock(UpdateExpression::class);
        $updateExpressionProperty->setValue($this->updateStatement, $mockUpdateExpression);
        
        $whereExpressionProperty = $reflection->getProperty('whereExpression');
        $whereExpressionProperty->setAccessible(true);
        $mockWhereExpression = Phake::mock(WhereExpression::class);
        $whereExpressionProperty->setValue($this->updateStatement, $mockWhereExpression);
        
        $result = $this->updateStatement->orderedExpressionMap();
        
        // Verify the correct order: UPDATE, WHERE
        self::assertCount(2, $result);
        self::assertSame($mockUpdateExpression, $result[0]);
        self::assertSame($mockWhereExpression, $result[1]);
    }
} 