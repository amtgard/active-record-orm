<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\WhereExpression;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class WhereExpressionTest extends AmtgardTestCase
{
    private WhereExpression $whereExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->whereExpression = new WhereExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->whereExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->whereExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->whereExpression, $this->mockFieldSet);
    }

    public function testWillEmit_withBinaryFieldQualifiers_returnsTrue(): void
    {
        $fieldOp = Phake::mock(FieldOperation::class);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([$fieldOp]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([]);
        
        $result = $this->whereExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testWillEmit_withUnaryFieldQualifiers_returnsTrue(): void
    {
        $fieldOp = Phake::mock(FieldOperation::class);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([$fieldOp]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([]);
        
        $result = $this->whereExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testWillEmit_withSetFieldQualifiers_returnsTrue(): void
    {
        $fieldOp = Phake::mock(FieldOperation::class);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([$fieldOp]);
        
        $result = $this->whereExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testWillEmit_withoutQualifiers_returnsFalse(): void
    {
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([]);
        
        $result = $this->whereExpression->willEmit();
        
        self::assertFalse($result);
    }

    public function testEmit_withBinaryFieldQualifiers_returnsWhereClause(): void
    {
        $fieldOp = Phake::mock(FieldOperation::class);
        $field = Phake::mock(FieldDefinition::class);
        
        Phake::when($field)->getName()->thenReturn('name');
        Phake::when($fieldOp)->getField()->thenReturn($field);
        Phake::when($fieldOp)->getOperation()->thenReturn(Operation::Equals);
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([$fieldOp]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([]);
        
        $result = $this->whereExpression->emit();
        
        self::assertEquals('WHERE name = :name', $result);
    }

    public function testEmit_withUnaryFieldQualifiers_returnsWhereClause(): void
    {
        $fieldOp = Phake::mock(FieldOperation::class);
        $field = Phake::mock(FieldDefinition::class);
        
        Phake::when($field)->getName()->thenReturn('email');
        Phake::when($fieldOp)->getField()->thenReturn($field);
        Phake::when($fieldOp)->getOperation()->thenReturn(Operation::IsNull);
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([$fieldOp]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([]);
        
        $result = $this->whereExpression->emit();
        
        self::assertEquals('WHERE email IS NULL', $result);
    }

    public function testEmit_withSetFieldQualifiers_returnsWhereClause(): void
    {
        $fieldOp = Phake::mock(FieldOperation::class);
        $field = Phake::mock(FieldDefinition::class);
        
        Phake::when($field)->getName()->thenReturn('status');
        Phake::when($fieldOp)->getField()->thenReturn($field);
        Phake::when($fieldOp)->getOperation()->thenReturn(Operation::In);
        Phake::when($fieldOp)->getValue()->thenReturn(['active', 'pending']);
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([$fieldOp]);
        
        $result = $this->whereExpression->emit();
        
        self::assertEquals('WHERE status IN (active, pending)', $result);
    }

    public function testEmit_withMultipleQualifiers_returnsCombinedWhereClause(): void
    {
        $binaryFieldOp = Phake::mock(FieldOperation::class);
        $unaryFieldOp = Phake::mock(FieldOperation::class);
        $field1 = Phake::mock(FieldDefinition::class);
        $field2 = Phake::mock(FieldDefinition::class);
        
        Phake::when($field1)->getName()->thenReturn('name');
        Phake::when($field2)->getName()->thenReturn('email');
        Phake::when($binaryFieldOp)->getField()->thenReturn($field1);
        Phake::when($binaryFieldOp)->getOperation()->thenReturn(Operation::Equals);
        Phake::when($unaryFieldOp)->getField()->thenReturn($field2);
        Phake::when($unaryFieldOp)->getOperation()->thenReturn(Operation::IsNotNull);
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([$binaryFieldOp]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])->thenReturn([$unaryFieldOp]);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])->thenReturn([]);
        
        $result = $this->whereExpression->emit();
        
        self::assertEquals('WHERE name = :name AND email IS NOT NULL', $result);
    }

    public function testPreparedParameters_withBinaryFieldQualifiers_returnsKeyValueMap(): void
    {
        $fieldOp = Phake::mock(FieldOperation::class);
        $field = Phake::mock(FieldDefinition::class);
        
        Phake::when($field)->getName()->thenReturn('name');
        Phake::when($fieldOp)->getField()->thenReturn($field);
        Phake::when($fieldOp)->getValue()->thenReturn('John Doe');
        
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])->thenReturn([$fieldOp]);
        
        $expectedParams = ['name' => 'John Doe'];
        Phake::when($this->mockFieldSet)->toKeyValueMap([$fieldOp])->thenReturn($expectedParams);
        
        $result = $this->whereExpression->preparedParameters();
        
        self::assertEquals($expectedParams, $result);
        Phake::verify($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike]);
        Phake::verify($this->mockFieldSet)->toKeyValueMap([$fieldOp]);
    }

    public function testEmitOperator_withEquals_returnsEqualsOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::Equals);
        
        self::assertEquals('=', $result);
    }

    public function testEmitOperator_withGreater_returnsGreaterOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::Greater);
        
        self::assertEquals('>', $result);
    }

    public function testEmitOperator_withGreaterOrEqual_returnsGreaterOrEqualOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::GreaterOrEqual);
        
        self::assertEquals('>=', $result);
    }

    public function testEmitOperator_withLess_returnsLessOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::Less);
        
        self::assertEquals('<', $result);
    }

    public function testEmitOperator_withLessOrEqual_returnsLessOrEqualOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::LessOrEqual);
        
        self::assertEquals('<=', $result);
    }

    public function testEmitOperator_withLike_returnsLikeOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::Like);
        
        self::assertEquals('LIKE', $result);
    }

    public function testEmitOperator_withNotLike_returnsNotLikeOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::NotLike);
        
        self::assertEquals('NOT LIKE', $result);
    }

    public function testEmitOperator_withIsNull_returnsIsNullOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::IsNull);
        
        self::assertEquals('IS NULL', $result);
    }

    public function testEmitOperator_withIsNotNull_returnsIsNotNullOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::IsNotNull);
        
        self::assertEquals('IS NOT NULL', $result);
    }

    public function testEmitOperator_withIn_returnsInOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::In);
        
        self::assertEquals('IN', $result);
    }

    public function testEmitOperator_withNotIn_returnsNotInOperator(): void
    {
        $result = WhereExpression::emitOperator(Operation::NotIn);
        
        self::assertEquals('NOT IN', $result);
    }

} 