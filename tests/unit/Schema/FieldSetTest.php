<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\Schema;
use Amtgard\ActiveRecordOrm\Utility\Constants;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\assertEquals;

class FieldSetTest extends AmtgardTestCase
{
    private FieldSet $fieldSet;
    private FieldOperation $mockFieldOperation1;
    private FieldOperation $mockFieldOperation2;
    private FieldDefinition $mockFieldDefinition1;
    private FieldDefinition $mockFieldDefinition2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fieldSet = FieldSet::builder()->build();
        
        // Create mock field definitions
        $this->mockFieldDefinition1 = Phake::mock(FieldDefinition::class);
        $this->mockFieldDefinition2 = Phake::mock(FieldDefinition::class);
        Phake::when($this->mockFieldDefinition1)->getName()->thenReturn('field1');
        Phake::when($this->mockFieldDefinition2)->getName()->thenReturn('field2');
        
        // Create mock field operations
        $this->mockFieldOperation1 = Phake::mock(FieldOperation::class);
        $this->mockFieldOperation2 = Phake::mock(FieldOperation::class);
        Phake::when($this->mockFieldOperation1)->getField()->thenReturn($this->mockFieldDefinition1);
        Phake::when($this->mockFieldOperation2)->getField()->thenReturn($this->mockFieldDefinition2);
        Phake::when($this->mockFieldOperation1)->getOperation()->thenReturn(Operation::Set);
        Phake::when($this->mockFieldOperation2)->getOperation()->thenReturn(Operation::Equals);
        Phake::when($this->mockFieldOperation1)->getValue()->thenReturn('value1');
        Phake::when($this->mockFieldOperation2)->getValue()->thenReturn('value2');
    }

    public function testClear_removesAllFields(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        $this->fieldSet->setField($this->mockFieldOperation2);
        
        self::assertEquals(2, count($this->fieldSet->getFieldNames()));
        
        $this->fieldSet->clear();
        
        self::assertEquals(0, count($this->fieldSet->getFieldNames()));
    }

    public function testClear_onEmptyFieldSet_doesNothing(): void
    {
        self::assertEquals(0, count($this->fieldSet->getFieldNames()));
        
        $this->fieldSet->clear();
        
        self::assertEquals(0, count($this->fieldSet->getFieldNames()));
    }

    public function testSubSet_withExistingFields_returnsSubset(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        $this->fieldSet->setField($this->mockFieldOperation2);
        
        $subset = $this->fieldSet->subSet(['field1']);
        
        self::assertInstanceOf(FieldSet::class, $subset);
        self::assertEquals(['field1'], $subset->getFieldNames());
        self::assertEquals($this->mockFieldOperation1, $subset->getField('field1'));
    }

    public function testSubSet_withNonExistingFields_throwsTypeError(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        $fieldName = 'non_existing_field';
        
        self::assertThrows(\InvalidArgumentException::class,
            fn() => $this->fieldSet->subSet([$fieldName]));

        try {
            $this->fieldSet->subSet([$fieldName]);
        } catch (\InvalidArgumentException $e) {
            assertEquals(sprintf(Constants::$FIELDSET_MISSING_ERROR, $fieldName), $e->getMessage());
        }
    }

    public function testSetField_addsFieldOperation(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        
        self::assertEquals(['field1'], $this->fieldSet->getFieldNames());
        self::assertEquals($this->mockFieldOperation1, $this->fieldSet->getField('field1'));
    }

    public function testSetField_overwritesExistingField(): void
    {
        $newFieldOperation = Phake::mock(FieldOperation::class);
        Phake::when($newFieldOperation)->getField()->thenReturn($this->mockFieldDefinition1);
        Phake::when($newFieldOperation)->getOperation()->thenReturn(Operation::Like);
        Phake::when($newFieldOperation)->getValue()->thenReturn('new_value');
        
        $this->fieldSet->setField($this->mockFieldOperation1);
        $this->fieldSet->setField($newFieldOperation);
        
        self::assertEquals(['field1'], $this->fieldSet->getFieldNames());
        self::assertEquals($newFieldOperation, $this->fieldSet->getField('field1'));
    }

    public function testHasField_withExistingField_returnsTrue(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        
        self::assertTrue($this->fieldSet->hasField($this->mockFieldDefinition1));
    }

    public function testHasField_withNonExistingField_returnsFalse(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        
        self::assertFalse($this->fieldSet->hasField($this->mockFieldDefinition2));
    }

    public function testMapRecord_withCallback_callsCallbackForEachField(): void
    {
        $mockSchema = Phake::mock(Schema::class);
        $mockRecordSet = Phake::mock(RecordSet::class);
        
        $mockField1 = Phake::mock(FieldDefinition::class);
        $mockField2 = Phake::mock(FieldDefinition::class);
        Phake::when($mockField1)->getName()->thenReturn('field1');
        Phake::when($mockField2)->getName()->thenReturn('field2');
        
        Phake::when($mockSchema)->getFields()->thenReturn(['field1' => $mockField1, 'field2' => $mockField2]);
        Phake::when($mockSchema)->getField('field1')->thenReturn($mockField1);
        Phake::when($mockSchema)->getField('field2')->thenReturn($mockField2);
        Phake::when($mockRecordSet)->__get('field1')->thenReturn('value1');
        Phake::when($mockRecordSet)->__get('field2')->thenReturn('value2');
        
        $callbackCalls = [];
        $callback = function($fieldName, $fieldOp) use (&$callbackCalls) {
            $callbackCalls[] = $fieldName;
        };
        
        $this->fieldSet->mapRecord($mockSchema, $mockRecordSet, $callback);
        
        self::assertEquals(['field1', 'field2'], $callbackCalls);
        self::assertEquals(2, count($this->fieldSet->getFieldNames()));
    }

    public function testMapRecord_withoutCallback_createsFieldOperations(): void
    {
        $mockSchema = Phake::mock(Schema::class);
        $mockRecordSet = Phake::mock(RecordSet::class);
        
        $mockField = Phake::mock(FieldDefinition::class);
        Phake::when($mockField)->getName()->thenReturn('field1');
        Phake::when($mockSchema)->getFields()->thenReturn(['field1' => $mockField]);
        Phake::when($mockSchema)->getField('field1')->thenReturn($mockField);
        Phake::when($mockRecordSet)->__get('field1')->thenReturn('value1');
        
        $this->fieldSet->mapRecord($mockSchema, $mockRecordSet);
        
        self::assertEquals(1, count($this->fieldSet->getFieldNames()));
        self::assertEquals('field1', $this->fieldSet->getFieldNames()[0]);
    }

    public function testSetFieldOperation_withExistingField_updatesOperation(): void
    {
        $mockSchema = Phake::mock(Schema::class);
        Phake::when($mockSchema)->hasField('field1')->thenReturn(true);
        Phake::when($mockSchema)->getField('field1')->thenReturn($this->mockFieldDefinition1);
        
        $this->fieldSet->setField($this->mockFieldOperation1);
        
        $this->fieldSet->setFieldOperation($mockSchema, 'field1', Operation::Like);
        
        Phake::verify($this->mockFieldOperation1)->setOperation(Operation::Like);
    }

    public function testSetFieldOperation_withNonExistingField_throwsException(): void
    {
        $mockSchema = Phake::mock(Schema::class);
        Phake::when($mockSchema)->hasField('non_existing')->thenThrow(new \InvalidArgumentException('Field not found'));
        
        $this->expectException(\InvalidArgumentException::class);
        
        $this->fieldSet->setFieldOperation($mockSchema, 'non_existing', Operation::Like);
    }

    public function testSetFieldValue_withValidField_createsAndReturnsFieldOperation(): void
    {
        $mockSchema = Phake::mock(Schema::class);
        Phake::when($mockSchema)->hasField('field1')->thenReturn(true);
        Phake::when($mockSchema)->getField('field1')->thenReturn($this->mockFieldDefinition1);
        
        $fieldOperation = $this->fieldSet->setFieldValue($mockSchema, 'field1', 'test_value', Operation::Set);
        
        self::assertInstanceOf(FieldOperation::class, $fieldOperation);
        self::assertEquals(['field1'], $this->fieldSet->getFieldNames());
        self::assertEquals($fieldOperation, $this->fieldSet->getField('field1'));
    }

    public function testSetFieldValue_withDefaultOperation_usesSetOperation(): void
    {
        $mockSchema = Phake::mock(Schema::class);
        Phake::when($mockSchema)->hasField('field1')->thenReturn(true);
        Phake::when($mockSchema)->getField('field1')->thenReturn($this->mockFieldDefinition1);
        
        $fieldOperation = $this->fieldSet->setFieldValue($mockSchema, 'field1', 'test_value');
        
        self::assertInstanceOf(FieldOperation::class, $fieldOperation);
        self::assertEquals(['field1'], $this->fieldSet->getFieldNames());
    }

    public function testGetField_withExistingField_returnsFieldOperation(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        
        $result = $this->fieldSet->getField('field1');
        
        self::assertEquals($this->mockFieldOperation1, $result);
    }

    public function testGetField_withNonExistingField_returnsNull(): void
    {
        $result = $this->fieldSet->getField('non_existing');
        
        self::assertNull($result);
    }

    public function testGetFieldNames_returnsAllFieldNames(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        $this->fieldSet->setField($this->mockFieldOperation2);
        
        $fieldNames = $this->fieldSet->getFieldNames();
        
        self::assertEquals(['field1', 'field2'], $fieldNames);
    }

    public function testGetFieldNames_onEmptyFieldSet_returnsEmptyArray(): void
    {
        $fieldNames = $this->fieldSet->getFieldNames();
        
        self::assertEquals([], $fieldNames);
    }

    public function testGetFieldsByOperation_withMatchingOperations_returnsFields(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1); // Operation::Set
        $this->fieldSet->setField($this->mockFieldOperation2); // Operation::Equals
        
        $fields = $this->fieldSet->getFieldsByOperation([Operation::Set]);
        
        self::assertEquals(1, count($fields));
        self::assertEquals($this->mockFieldOperation1, $fields[0]);
    }

    public function testGetFieldsByOperation_withNoMatchingOperations_returnsEmptyArray(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1); // Operation::Set
        
        $fields = $this->fieldSet->getFieldsByOperation([Operation::Like]);
        
        self::assertEquals(0, count($fields));
    }

    public function testUpdateSetOperationToEquals_updatesSetOperationsToEquals(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1); // Operation::Set
        $this->fieldSet->setField($this->mockFieldOperation2); // Operation::Equals
        
        $this->fieldSet->updateSetOperationToEquals();
        
        Phake::verify($this->mockFieldOperation1)->setOperation(Operation::Equals);
        Phake::verify($this->mockFieldOperation2, Phake::never())->setOperation(Phake::anyParameters());
    }

    public function testUpdateSetOperationToEquals_withNoSetOperations_doesNothing(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation2); // Operation::Equals
        
        $this->fieldSet->updateSetOperationToEquals();
        
        Phake::verify($this->mockFieldOperation2, Phake::never())->setOperation(Phake::anyParameters());
    }

    public function testOpsToNames_withValidOperations_returnsFieldNames(): void
    {
        $operations = [$this->mockFieldOperation1, $this->mockFieldOperation2];
        
        $names = FieldSet::opsToNames($operations);
        
        self::assertEquals(['field1', 'field2'], $names);
    }

    public function testOpsToNames_withEmptyArray_returnsEmptyArray(): void
    {
        $names = FieldSet::opsToNames([]);
        
        self::assertEquals([], $names);
    }

    public function testOpsToValues_withValidOperations_returnsValues(): void
    {
        $operations = [$this->mockFieldOperation1, $this->mockFieldOperation2];
        
        $values = FieldSet::opsToValues($operations);
        
        self::assertEquals(['value1', 'value2'], $values);
    }

    public function testOpsToValues_withEmptyArray_returnsEmptyArray(): void
    {
        $values = FieldSet::opsToValues([]);
        
        self::assertEquals([], $values);
    }

    public function testOpsToKeyValueMap_withValidOperations_returnsKeyValueMap(): void
    {
        $operations = [$this->mockFieldOperation1, $this->mockFieldOperation2];
        
        $map = FieldSet::opsToKeyValueMap($operations);
        
        self::assertEquals(['field1' => 'value1', 'field2' => 'value2'], $map);
    }

    public function testOpsToKeyValueMap_withEmptyArray_returnsEmptyArray(): void
    {
        $map = FieldSet::opsToKeyValueMap([]);
        
        self::assertEquals([], $map);
    }

    public function testGetFieldMap_withFields_returnsNameToValueMap(): void
    {
        $this->fieldSet->setField($this->mockFieldOperation1);
        $this->fieldSet->setField($this->mockFieldOperation2);

        $result = $this->fieldSet->getFieldMap();

        self::assertEquals(['field1' => 'value1', 'field2' => 'value2'], $result);
    }
} 