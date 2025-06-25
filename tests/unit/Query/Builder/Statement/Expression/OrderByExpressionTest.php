<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\OrderByExpression;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class OrderByExpressionTest extends AmtgardTestCase
{
    private OrderByExpression $orderByExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->orderByExpression = new OrderByExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->orderByExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->orderByExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->orderByExpression, $this->mockFieldSet);
    }

    public function testWillEmit_withOrderByOperations_returnsTrue(): void
    {
        // Set up reflection to access private property
        $reflection = new \ReflectionClass($this->orderByExpression);
        $orderByOperationsProperty = $reflection->getProperty('orderByOperations');
        $orderByOperationsProperty->setAccessible(true);
        $orderByOperationsProperty->setValue($this->orderByExpression, ['field1' => OrderBy::ASC, 'field2' => OrderBy::DESC]);
        
        $result = $this->orderByExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testWillEmit_withoutOrderByOperations_returnsFalse(): void
    {
        // Set up reflection to access private property
        $reflection = new \ReflectionClass($this->orderByExpression);
        $orderByOperationsProperty = $reflection->getProperty('orderByOperations');
        $orderByOperationsProperty->setAccessible(true);
        $orderByOperationsProperty->setValue($this->orderByExpression, []);
        
        $result = $this->orderByExpression->willEmit();
        
        self::assertFalse($result);
    }

    public function testEmit_withOrderByOperations_returnsOrderByClause(): void
    {
        // Set up reflection to access private property
        $reflection = new \ReflectionClass($this->orderByExpression);
        $orderByOperationsProperty = $reflection->getProperty('orderByOperations');
        $orderByOperationsProperty->setAccessible(true);
        $orderByOperationsProperty->setValue($this->orderByExpression, [
            'field1' => OrderBy::ASC,
            'field2' => OrderBy::DESC
        ]);
        
        $result = $this->orderByExpression->emit();
        
        self::assertEquals('ORDER BY field1 ASC, field2 DESC', $result);
    }

    public function testEmit_withSingleOrderByOperation_returnsSingleOrderByClause(): void
    {
        // Set up reflection to access private property
        $reflection = new \ReflectionClass($this->orderByExpression);
        $orderByOperationsProperty = $reflection->getProperty('orderByOperations');
        $orderByOperationsProperty->setAccessible(true);
        $orderByOperationsProperty->setValue($this->orderByExpression, [
            'name' => OrderBy::ASC
        ]);
        
        $result = $this->orderByExpression->emit();
        
        self::assertEquals('ORDER BY name ASC', $result);
    }

    public function testPreparedParameters_returnsEmptyArray(): void
    {
        $result = $this->orderByExpression->preparedParameters();
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }
} 