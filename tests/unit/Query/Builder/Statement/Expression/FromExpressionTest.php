<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\FromExpression;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class FromExpressionTest extends AmtgardTestCase
{
    private FromExpression $fromExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->fromExpression = new FromExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->fromExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->fromExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->fromExpression, $this->mockFieldSet);
    }

    public function testWillEmit_alwaysReturnsTrue(): void
    {
        $result = $this->fromExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testEmit_returnsFromClauseWithTableName(): void
    {
        Phake::when($this->mockTableSchema)->getTableName()->thenReturn('test_table');
        
        $result = $this->fromExpression->emit();
        
        self::assertEquals('FROM test_table', $result);
        Phake::verify($this->mockTableSchema)->getTableName();
    }

    public function testPreparedParameters_returnsEmptyArray(): void
    {
        $result = $this->fromExpression->preparedParameters();
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }
} 