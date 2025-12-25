<?php

namespace Tests\Unit\Factory;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Factory\EntityFactory;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class EntityFactoryTest extends AmtgardTestCase
{
    private EntityMapper $mockMapper;
    private Table $mockTable;
    private TableSchema $mockTableSchema;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockMapper = Phake::mock(EntityMapper::class);
        $this->mockTable = Phake::mock(Table::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);

        Phake::when($this->mockMapper)->getTable()->thenReturn($this->mockTable);
        Phake::when($this->mockTable)->getTableSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockMapper)->getChanges()->thenReturn(['field1' => 'value1', 'field2' => 'value2']);
    }

    public function testBuild_createsEntityInterface(): void
    {
        $entity = EntityFactory::build($this->mockMapper);
        
        self::assertInstanceOf(EntityInterface::class, $entity);
    }

    public function testBuild_usesMapperTableSchema(): void
    {
        $entity = EntityFactory::build($this->mockMapper);
        
        Phake::verify($this->mockMapper)->getTable();
        Phake::verify($this->mockTable)->getTableSchema();
        self::assertInstanceOf(EntityInterface::class, $entity);
    }

    public function testBuild_usesMapperChanges(): void
    {
        $changes = ['id' => 1, 'name' => 'test'];
        Phake::when($this->mockMapper)->getChanges()->thenReturn($changes);
        
        $entity = EntityFactory::build($this->mockMapper);
        
        Phake::verify($this->mockMapper)->getChanges();
        self::assertInstanceOf(EntityInterface::class, $entity);
    }

    public function testBuild_createsEntityWithEmptyChanges(): void
    {
        Phake::when($this->mockMapper)->getChanges()->thenReturn([]);
        
        $entity = EntityFactory::build($this->mockMapper);
        
        Phake::verify($this->mockMapper)->getChanges();
        self::assertInstanceOf(EntityInterface::class, $entity);
    }

    public function testBuild_createsEntityWithMultipleFields(): void
    {
        $changes = [
            'id' => 42,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2023-01-01 12:00:00'
        ];
        Phake::when($this->mockMapper)->getChanges()->thenReturn($changes);
        
        $entity = EntityFactory::build($this->mockMapper);
        
        self::assertInstanceOf(EntityInterface::class, $entity);
        Phake::verify($this->mockMapper)->getChanges();
    }

    public function testBuild_callsAllRequiredMapperMethods(): void
    {
        $entity = EntityFactory::build($this->mockMapper);
        
        // Verify all expected calls to the mapper
        Phake::verify($this->mockMapper)->getTable();
        Phake::verify($this->mockMapper)->getChanges();
        Phake::verify($this->mockTable)->getTableSchema();
        self::assertInstanceOf(EntityInterface::class, $entity);
    }
}

