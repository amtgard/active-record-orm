<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\InsertExpression;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class InsertExpressionTest extends AmtgardTestCase
{
    private InsertExpression $insertExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->insertExpression = new InsertExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->insertExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->insertExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->insertExpression, $this->mockFieldSet);
    }

    public function testWillEmit_alwaysReturnsTrue(): void
    {
        $result = $this->insertExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testEmit_withSetAndEqualsOperations_returnsInsertClause(): void
    {
        // Mock field operations
        $fieldOp1 = Phake::mock(FieldOperation::class);
        $fieldOp2 = Phake::mock(FieldOperation::class);
        
        $field1 = Phake::mock(FieldDefinition::class);
        $field2 = Phake::mock(FieldDefinition::class);
        
        Phake::when($field1)->getName()->thenReturn('name');
        Phake::when($field2)->getName()->thenReturn('email');
        
        Phake::when($fieldOp1)->getField()->thenReturn($field1);
        Phake::when($fieldOp2)->getField()->thenReturn($field2);
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Set, Operation::Equals])->thenReturn([$fieldOp1, $fieldOp2]);
        Phake::when($this->mockTableSchema)->getTableName()->thenReturn('users');
        
        $result = $this->insertExpression->emit();
        
        self::assertEquals('INSERT INTO users (name, email) VALUES (:name, :email)', $result);
        Phake::verify($this->mockFieldSet)->getFieldsByOperation([Operation::Set, Operation::Equals]);
        Phake::verify($this->mockTableSchema)->getTableName();
    }

    public function testEmit_withSingleOperation_returnsSingleInsertClause(): void
    {
        // Mock field operation
        $fieldOp = Phake::mock(FieldOperation::class);
        $field = Phake::mock(FieldDefinition::class);
        
        Phake::when($field)->getName()->thenReturn('status');
        Phake::when($fieldOp)->getField()->thenReturn($field);
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Set, Operation::Equals])->thenReturn([$fieldOp]);
        Phake::when($this->mockTableSchema)->getTableName()->thenReturn('orders');
        
        $result = $this->insertExpression->emit();
        
        self::assertEquals('INSERT INTO orders (status) VALUES (:status)', $result);
    }

    public function testPreparedParameters_withSetAndEqualsOperations_returnsKeyValueMap(): void
    {
        // Mock field operations
        $fieldOp1 = Phake::mock(FieldOperation::class);
        $fieldOp2 = Phake::mock(FieldOperation::class);
        
        $field1 = Phake::mock(FieldDefinition::class);
        $field2 = Phake::mock(FieldDefinition::class);
        
        Phake::when($field1)->getName()->thenReturn('name');
        Phake::when($field2)->getName()->thenReturn('email');
        
        Phake::when($fieldOp1)->getField()->thenReturn($field1);
        Phake::when($fieldOp1)->getValue()->thenReturn('John Doe');
        Phake::when($fieldOp2)->getField()->thenReturn($field2);
        Phake::when($fieldOp2)->getValue()->thenReturn('john@example.com');
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Set, Operation::Equals])->thenReturn([$fieldOp1, $fieldOp2]);
        
        // Mock the static method
        $expectedParams = ['name' => 'John Doe', 'email' => 'john@example.com'];
        Phake::when($this->mockFieldSet)->toKeyValueMap([$fieldOp1, $fieldOp2])->thenReturn($expectedParams);
        
        $result = $this->insertExpression->preparedParameters();
        
        self::assertEquals($expectedParams, $result);
        Phake::verify($this->mockFieldSet)->getFieldsByOperation([Operation::Set, Operation::Equals]);
        Phake::verify($this->mockFieldSet)->toKeyValueMap([$fieldOp1, $fieldOp2]);
    }
} 