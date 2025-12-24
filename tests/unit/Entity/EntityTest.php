<?php

namespace Tests\Unit;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class EntityTest extends AmtgardTestCase
{
    private Entity $entity;
    private ResultSet $mockResultSet;
    private TableSchema $mockTableSchema;
    private Table $mockTable;
    private FieldDefinition $mockPrimaryKey;
    private EntityMapper $mockEntityMapper;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockResultSet = Phake::mock(ResultSet::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockTable = Phake::mock(Table::class);
        $this->mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $this->mockNameField = Phake::mock(FieldDefinition::class);
        $this->mockEmailField = Phake::mock(FieldDefinition::class);
        $this->mockEntityMapper = Phake::mock(EntityMapper::class);

        Phake::when($this->mockEntityMapper)->getTable()->thenReturn($this->mockTable);

        // Mock the primary key field definition
        Phake::when($this->mockPrimaryKey)->getName()->thenReturn('id');
        Phake::when($this->mockTableSchema)->getField('name')->thenReturn($this->mockNameField);
        Phake::when($this->mockTableSchema)->getField('email')->thenReturn($this->mockEmailField);

        // Mock the table schema to return the primary key
        Phake::when($this->mockTableSchema)->getPrimaryKey()->thenReturn($this->mockPrimaryKey);
        
        // Mock the result set to return field map
        Phake::when($this->mockResultSet)->getFieldMap()->thenReturn([
            'id' => 123,
            'name' => 'Test Entity',
            'email' => 'test@example.com'
        ]);


        $this->entity = Entity::builder()
            ->resultSet($this->mockResultSet)
            ->schema($this->mockTableSchema)
            ->build();
    }

    public function testGetPrimaryKey_returnsFieldDefinitionWithCorrectValue(): void
    {
        $result = $this->entity->getPrimaryKey();
        
        self::assertInstanceOf(FieldDefinition::class, $result);
        self::assertEquals('id', $result->getName());
        self::assertEquals(123, $result->getValue());
        
        Phake::verify($this->mockTableSchema)->getPrimaryKey();
    }

    public function testFlush_withDirtyEntity_savesChangesToTable(): void
    {
        // Make the entity dirty by setting a field
        $this->entity->name = 'Updated Name';
        
        // Mock the table interface methods
        Phake::when($this->mockTable)->clear()->thenReturnSelf();
        Phake::when($this->mockTable)->save()->thenReturnSelf();
        Phake::when($this->mockTable)->getPrimaryKeyValue()->thenReturn(123);
        
        $this->entity->persist($this->mockEntityMapper);
        
        // Verify that clear was called
        Phake::verify($this->mockTable)->clear();
        
        // Verify that the primary key was set
        Phake::verify($this->mockTable)->id = 123;
        
        // Verify that the changed field was set
        Phake::verify($this->mockTable)->name = 'Updated Name';
        
        // Verify that save was called
        Phake::verify($this->mockTable)->save();
    }

    public function testGetChanges_returnsArrayOfChangedFields(): void
    {
        // Initially no changes
        $result = $this->entity->getChanges();
        self::assertEquals([], $result);
        
        // Set some fields to make changes
        $this->entity->name = 'New Name';
        $this->entity->email = 'new@example.com';
        
        $result = $this->entity->getChanges();
        
        self::assertEquals([
            'name' => 'New Name',
            'email' => 'new@example.com'
        ], $result);
    }

    public function testIsDirty_withChanges_returnsTrue(): void
    {
        // Initially not dirty
        self::assertFalse($this->entity->isDirty());
        
        // Set a field to make it dirty
        $this->entity->name = 'Updated Name';
        
        self::assertTrue($this->entity->isDirty());
    }
}
