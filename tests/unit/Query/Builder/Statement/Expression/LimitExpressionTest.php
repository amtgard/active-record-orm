<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\LimitExpression;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class LimitExpressionTest extends AmtgardTestCase
{
    private LimitExpression $limitExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->limitExpression = new LimitExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->limitExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->limitExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->limitExpression, $this->mockFieldSet);
    }

    public function testWillEmit_withLimitSet_returnsTrue(): void
    {
        // Set up reflection to access protected property
        $reflection = new \ReflectionClass($this->limitExpression);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $withLimitProperty->setValue($this->limitExpression, true);
        
        $result = $this->limitExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testWillEmit_withoutLimitSet_returnsFalse(): void
    {
        // Set up reflection to access protected property
        $reflection = new \ReflectionClass($this->limitExpression);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $withLimitProperty->setValue($this->limitExpression, false);
        
        $result = $this->limitExpression->willEmit();
        
        self::assertFalse($result);
    }

    public function testEmit_withOffsetOnly_returnsLimitClause(): void
    {
        // Set up reflection to access protected properties
        $reflection = new \ReflectionClass($this->limitExpression);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $withLimitProperty->setValue($this->limitExpression, true);
        
        $offsetProperty = $reflection->getProperty('offset');
        $offsetProperty->setAccessible(true);
        $offsetProperty->setValue($this->limitExpression, 10);
        
        $rowCountProperty = $reflection->getProperty('rowCount');
        $rowCountProperty->setAccessible(true);
        $rowCountProperty->setValue($this->limitExpression, null);
        
        $result = $this->limitExpression->emit();
        
        self::assertEquals('LIMIT 10', $result);
    }

    public function testEmit_withOffsetAndRowCount_returnsLimitClause(): void
    {
        // Set up reflection to access protected properties
        $reflection = new \ReflectionClass($this->limitExpression);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $withLimitProperty->setValue($this->limitExpression, true);
        
        $offsetProperty = $reflection->getProperty('offset');
        $offsetProperty->setAccessible(true);
        $offsetProperty->setValue($this->limitExpression, 20);
        
        $rowCountProperty = $reflection->getProperty('rowCount');
        $rowCountProperty->setAccessible(true);
        $rowCountProperty->setValue($this->limitExpression, 50);
        
        $result = $this->limitExpression->emit();
        
        self::assertEquals('LIMIT 20, 50', $result);
    }

    public function testEmit_withZeroOffsetAndRowCount_returnsLimitClause(): void
    {
        // Set up reflection to access protected properties
        $reflection = new \ReflectionClass($this->limitExpression);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $withLimitProperty->setValue($this->limitExpression, true);
        
        $offsetProperty = $reflection->getProperty('offset');
        $offsetProperty->setAccessible(true);
        $offsetProperty->setValue($this->limitExpression, 0);
        
        $rowCountProperty = $reflection->getProperty('rowCount');
        $rowCountProperty->setAccessible(true);
        $rowCountProperty->setValue($this->limitExpression, 25);
        
        $result = $this->limitExpression->emit();
        
        self::assertEquals('LIMIT 0, 25', $result);
    }

    public function testPreparedParameters_returnsEmptyArray(): void
    {
        $result = $this->limitExpression->preparedParameters();
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }
} 