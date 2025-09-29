<?php

namespace Tests\Unit;

use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\Schema;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class ResultSetTest extends AmtgardTestCase
{
    private ResultSet $resultSet;
    private Schema $mockSchema;
    private RecordSet $mockRecordSet;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockSchema = Phake::mock(Schema::class);
        $this->mockRecordSet = Phake::mock(RecordSet::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->resultSet = ResultSet::builder()
            ->schema($this->mockSchema)
            ->recordSet($this->mockRecordSet)
            ->fieldSet($this->mockFieldSet)
            ->build();
    }

    public function testGet_withExistingField_returnsFieldValue(): void
    {
        $mockFieldDefinition = Phake::mock(FieldDefinition::class);
        $mockFieldOperation = Phake::mock(FieldOperation::class);
        
        Phake::when($this->mockSchema)->hasField('name')->thenReturn(true);
        Phake::when($this->mockFieldSet)->getField('name')->thenReturn($mockFieldOperation);
        Phake::when($mockFieldOperation)->getValue()->thenReturn('John Doe');
        
        $result = $this->resultSet->name;
        
        self::assertEquals('John Doe', $result);
        Phake::verify($this->mockSchema)->hasField('name');
        Phake::verify($this->mockFieldSet)->getField('name');
    }

    public function testGet_withNonExistentField_returnsNull(): void
    {
        $mockFieldDefinition = Phake::mock(FieldDefinition::class);
        
        Phake::when($this->mockSchema)->hasField('nonexistent')->thenReturn(true);
        Phake::when($this->mockFieldSet)->getField('nonexistent')->thenReturn(null);
        
        $result = $this->resultSet->nonexistent;
        
        self::assertNull($result);
        Phake::verify($this->mockSchema)->hasField('nonexistent');
        Phake::verify($this->mockFieldSet)->getField('nonexistent');
    }

    public function testNext_withMoreRecords_returnsTrueAndMapsRecord(): void
    {
        Phake::when($this->mockRecordSet)->next()->thenReturn(true);
        Phake::when($this->mockFieldSet)->clear()->thenReturnSelf();
        Phake::when($this->mockFieldSet)->mapRecord($this->mockSchema, $this->mockRecordSet)->thenReturnSelf();
        
        $result = $this->resultSet->next();
        
        self::assertTrue($result);
        Phake::verify($this->mockRecordSet)->next();
        Phake::verify($this->mockFieldSet)->clear();
        Phake::verify($this->mockFieldSet)->mapRecord($this->mockSchema, $this->mockRecordSet);
    }

    public function testNext_withNoMoreRecords_returnsFalseAndStillMapsRecord(): void
    {
        Phake::when($this->mockRecordSet)->next()->thenReturn(false);
        Phake::when($this->mockFieldSet)->clear()->thenReturnSelf();
        Phake::when($this->mockFieldSet)->mapRecord($this->mockSchema, $this->mockRecordSet)->thenReturnSelf();
        
        $result = $this->resultSet->next();
        
        self::assertFalse($result);
        Phake::verify($this->mockRecordSet)->next();
        Phake::verify($this->mockFieldSet)->clear();
        Phake::verify($this->mockFieldSet)->mapRecord($this->mockSchema, $this->mockRecordSet);
    }

    public function testGetFieldMap_returnsFieldNameToValueMap(): void
    {
        // Mock field definitions
        $mockField1 = Phake::mock(FieldDefinition::class);
        $mockField2 = Phake::mock(FieldDefinition::class);
        
        // Mock field names
        Phake::when($mockField1)->getName()->thenReturn('id');
        Phake::when($mockField2)->getName()->thenReturn('name');
        
        // Mock schema to return fields
        Phake::when($this->mockSchema)->getFields()->thenReturn([$mockField1, $mockField2]);
        
        // Mock record set to return values for fields
        Phake::when($this->mockRecordSet)->id->thenReturn(1);
        Phake::when($this->mockRecordSet)->name->thenReturn('John Doe');
        
        $fieldMap = $this->resultSet->getFieldMap();
        
        $expectedMap = [
            'id' => 1,
            'name' => 'John Doe'
        ];
        
        self::assertEquals($expectedMap, $fieldMap);
        Phake::verify($this->mockSchema)->getFields();
        Phake::verify($mockField1)->getName();
        Phake::verify($mockField2)->getName();
        Phake::verify($this->mockRecordSet)->id;
        Phake::verify($this->mockRecordSet)->name;
    }
} 