<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\DeleteExpression;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class DeleteExpressionTest extends AmtgardTestCase
{
    private DeleteExpression $deleteExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->deleteExpression = new DeleteExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->deleteExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->deleteExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->deleteExpression, $this->mockFieldSet);
    }

    public function testWillEmit_alwaysReturnsTrue(): void
    {
        $result = $this->deleteExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testEmit_returnsDeleteClauseWithTableName(): void
    {
        Phake::when($this->mockTableSchema)->getTableName()->thenReturn('users');
        
        $result = $this->deleteExpression->emit();
        
        self::assertEquals('DELETE FROM users', $result);
        Phake::verify($this->mockTableSchema)->getTableName();
    }

    public function testEmit_withDifferentTableName_returnsDeleteClauseWithCorrectTableName(): void
    {
        Phake::when($this->mockTableSchema)->getTableName()->thenReturn('orders');
        
        $result = $this->deleteExpression->emit();
        
        self::assertEquals('DELETE FROM orders', $result);
        Phake::verify($this->mockTableSchema)->getTableName();
    }

    public function testPreparedParameters_returnsEmptyArray(): void
    {
        $result = $this->deleteExpression->preparedParameters();
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }
} 