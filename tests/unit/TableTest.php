<?php

namespace Tests\Unit;

use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\anything;

class TableTest extends AmtgardTestCase
{
    private Table $table;
    private Database $mockDatabase;
    private TableSchema $mockTableSchema;
    private QueryBuilder $mockQueryBuilder;
    private DataAccessPolicy $mockDataAccessPolicy;
    private RecordSet $mockRecordSet;
    private FieldSet $mockFieldSet;
    private FieldDefinition $mockPrimaryKey;
    private TableFactory $mockTableFactory;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockQueryBuilder = Phake::mock(QueryBuilder::class);
        $this->mockDataAccessPolicy = Phake::mock(DataAccessPolicy::class);
        $this->mockRecordSet = Phake::mock(RecordSet::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        $this->mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $this->mockTableFactory = Phake::mock(TableFactory::class);
        
        // Mock TableFactory
        Phake::whenStatic($this->mockTableFactory)->buildQueryBuilder($this->mockDataAccessPolicy, 'test_table')->thenReturn($this->mockQueryBuilder);
        
        $this->table = Table::builder()
            ->database($this->mockDatabase)
            ->tableSchema($this->mockTableSchema)
            ->queryBuilder($this->mockQueryBuilder)
            ->dataAccessPolicy($this->mockDataAccessPolicy)
            ->fieldSet($this->mockFieldSet)
            ->tableName('test_table')
            ->build();
    }

    public function testSet_withValidValue_setsFieldValue(): void
    {
        $mockFieldOperation = Phake::mock(FieldOperation::class);
        
        Phake::when($this->mockFieldSet)->setFieldValue($this->mockTableSchema, 'name', 'John Doe', Operation::Set)->thenReturn($mockFieldOperation);
        Phake::when($this->mockQueryBuilder)->__set('name', $mockFieldOperation)->thenReturnSelf();
        
        $this->table->name = 'John Doe';
        
        Phake::verify($this->mockFieldSet)->setFieldValue($this->mockTableSchema, 'name', 'John Doe', Operation::Set);
        Phake::verify($this->mockQueryBuilder)->__set('name', $mockFieldOperation);
    }

    public function testGet_withRecordSetField_returnsRecordSetValue(): void
    {
        Phake::when($this->mockRecordSet)->hasField('name')->thenReturn(true);
        Phake::when($this->mockRecordSet)->__get('name')->thenReturn('John Doe');
        
        // Set recordSet using reflection
        $reflection = new \ReflectionClass($this->table);
        $recordSetProperty = $reflection->getProperty('recordSet');
        $recordSetProperty->setAccessible(true);
        $recordSetProperty->setValue($this->table, $this->mockRecordSet);
        
        $result = $this->table->name;
        
        self::assertEquals('John Doe', $result);
        Phake::verify($this->mockRecordSet)->hasField('name');
        Phake::verify($this->mockRecordSet)->__get('name');
    }

    public function testGet_withFieldSetField_returnsFieldSetValue(): void
    {
        $mockFieldOperation = Phake::mock(FieldOperation::class);
        
        Phake::when($this->mockTableSchema)->hasField('name')->thenReturn(true);
        Phake::when($this->mockFieldSet)->getField('name')->thenReturn($mockFieldOperation);
        Phake::when($mockFieldOperation)->getValue()->thenReturn('John Doe');
        
        $result = $this->table->name;
        
        self::assertEquals('John Doe', $result);
        Phake::verify($this->mockTableSchema)->hasField('name');
        Phake::verify($this->mockFieldSet)->getField('name');
    }

    public function testClear_resetsTableState(): void
    {
        Phake::when($this->mockFieldSet)->clear()->thenReturnSelf();
        $this->table->tableFactory = $this->mockTableFactory;

        $this->table->clear();
        
        Phake::verify($this->mockFieldSet)->clear();
        Phake::verifyStatic($this->mockTableFactory)->buildQueryBuilder(self::anything(), self::anything());
    }

    public function testOrderBy_delegatesToQueryBuilder(): void
    {
        $this->table->orderBy('name', OrderBy::ASC);
        
        Phake::verify($this->mockQueryBuilder)->orderBy('name', OrderBy::ASC);
    }

    public function testSelect_delegatesToQueryBuilder(): void
    {
        $this->table->select(['name', 'email']);
        
        Phake::verify($this->mockQueryBuilder)->select(['name', 'email']);
    }

    public function testFind_withLimit_appliesLimitAndReturnsRecordCount(): void
    {
        $mockQuery = Phake::mock(Query::class);
        
        // Set withLimit using reflection
        $reflection = new \ReflectionClass($this->table);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $withLimitProperty->setValue($this->table, true);
        
        $offsetProperty = $reflection->getProperty('offset');
        $offsetProperty->setAccessible(true);
        $offsetProperty->setValue($this->table, 10);
        
        $rowCountProperty = $reflection->getProperty('rowCount');
        $rowCountProperty->setAccessible(true);
        $rowCountProperty->setValue($this->table, 20);
        
        Phake::when($this->mockQueryBuilder)->limit(10, 20)->thenReturnSelf();
        Phake::when($this->mockQueryBuilder)->find()->thenReturnSelf();
        Phake::when($this->mockQueryBuilder)->compile()->thenReturn($mockQuery);
        Phake::when($this->mockDataAccessPolicy)->applyQueryPolicy($mockQuery)->thenReturn($this->mockRecordSet);
        Phake::when($this->mockRecordSet)->size()->thenReturn(5);
        Phake::when($this->mockFieldSet)->clear()->thenReturnSelf();
        
        $result = $this->table->find();
        
        self::assertEquals(5, $result);
        Phake::verify($this->mockQueryBuilder)->limit(10, 20);
        Phake::verify($this->mockQueryBuilder)->find();
        Phake::verify($this->mockDataAccessPolicy)->applyQueryPolicy($mockQuery);
    }

    public function testCount_returnsRecordCount(): void
    {
        $mockQuery = Phake::mock(Query::class);
        
        Phake::when($this->mockQueryBuilder)->count('row_count')->thenReturnSelf();
        Phake::when($this->mockQueryBuilder)->compile()->thenReturn($mockQuery);
        Phake::when($this->mockDataAccessPolicy)->applyQueryPolicy($mockQuery)->thenReturn($this->mockRecordSet);
        Phake::when($this->mockRecordSet)->size()->thenReturn(10);
        Phake::when($this->mockFieldSet)->clear()->thenReturnSelf();
        Phake::when($this->mockRecordSet)->next()->thenReturn(false);
        
        $result = $this->table->count();
        
        self::assertEquals(10, $result);
        Phake::verify($this->mockQueryBuilder)->count('row_count');
        Phake::verify($this->mockDataAccessPolicy)->applyQueryPolicy($mockQuery);
    }

    public function testPage_setsPaginationParameters(): void
    {
        $result = $this->table->page(20, 2);
        
        self::assertSame($this->table, $result);
        
        $reflection = new \ReflectionClass($this->table);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $offsetProperty = $reflection->getProperty('offset');
        $offsetProperty->setAccessible(true);
        $rowCountProperty = $reflection->getProperty('rowCount');
        $rowCountProperty->setAccessible(true);
        
        self::assertTrue($withLimitProperty->getValue($this->table));
        self::assertEquals(40, $offsetProperty->getValue($this->table));
        self::assertEquals(2, $rowCountProperty->getValue($this->table));
    }

    public function testLimit_setsLimitParameters(): void
    {
        $this->table->limit(10, 20);
        
        $reflection = new \ReflectionClass($this->table);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $withLimitProperty->setAccessible(true);
        $offsetProperty = $reflection->getProperty('offset');
        $offsetProperty->setAccessible(true);
        $rowCountProperty = $reflection->getProperty('rowCount');
        $rowCountProperty->setAccessible(true);
        
        self::assertTrue($withLimitProperty->getValue($this->table));
        self::assertEquals(10, $offsetProperty->getValue($this->table));
        self::assertEquals(20, $rowCountProperty->getValue($this->table));
    }

    public function testGetResultSet_returnsResultSet(): void
    {
        // Set recordSet using reflection
        $reflection = new \ReflectionClass($this->table);
        $recordSetProperty = $reflection->getProperty('recordSet');
        $recordSetProperty->setAccessible(true);
        $recordSetProperty->setValue($this->table, $this->mockRecordSet);
        
        $result = $this->table->getResultSet();
        
        self::assertInstanceOf(ResultSet::class, $result);
    }

    public function testSave_executesUpsertQuery(): void
    {
        $mockQuery = Phake::mock(Query::class);
        
        Phake::when($this->mockTableSchema)->getPrimaryKey()->thenReturn($this->mockPrimaryKey);
        Phake::when($this->mockPrimaryKey)->getName()->thenReturn('id');
        Phake::when($this->mockDatabase)->getLastInsertId()->thenReturn(123);
        Phake::when($this->mockQueryBuilder)->upsert(Phake::anyParameters())->thenReturnSelf();
        Phake::when($this->mockQueryBuilder)->compile()->thenReturn($mockQuery);

        $this->table->save();
        
        Phake::verify($this->mockQueryBuilder)->upsert(Phake::anyParameters());
        Phake::verify($this->mockDataAccessPolicy)->applyQueryPolicy($mockQuery);
    }

    public function testDelete_executesDeleteQuery(): void
    {
        $mockQuery = Phake::mock(Query::class);
        
        Phake::when($this->mockQueryBuilder)->delete()->thenReturnSelf();
        Phake::when($this->mockQueryBuilder)->compile()->thenReturn($mockQuery);

        $this->table->delete();
        
        Phake::verify($this->mockQueryBuilder)->delete();
        Phake::verify($this->mockDataAccessPolicy)->applyQueryPolicy($mockQuery);
    }

    public function testSize_withResults_returnsRecordSetSize(): void
    {
        // Set recordSet using reflection
        $reflection = new \ReflectionClass($this->table);
        $recordSetProperty = $reflection->getProperty('recordSet');
        $recordSetProperty->setAccessible(true);
        $recordSetProperty->setValue($this->table, $this->mockRecordSet);
        
        Phake::when($this->mockRecordSet)->size()->thenReturn(5);
        
        $result = $this->table->size();
        
        self::assertEquals(5, $result);
        Phake::verify($this->mockRecordSet)->size();
    }

    public function testSize_withoutResults_returnsZero(): void
    {
        // Set recordSet using reflection
        $reflection = new \ReflectionClass($this->table);
        $recordSetProperty = $reflection->getProperty('recordSet');
        $recordSetProperty->setAccessible(true);
        $recordSetProperty->setValue($this->table, $this->mockRecordSet);

        Phake::when($this->mockRecordSet)->size()->thenReturn(0);

        $result = $this->table->size();
        
        self::assertEquals(0, $result);
    }

    public function testNext_advancesRecordSetAndMapsRecord(): void
    {
        // Set recordSet using reflection
        $reflection = new \ReflectionClass($this->table);
        $recordSetProperty = $reflection->getProperty('recordSet');
        $recordSetProperty->setAccessible(true);
        $recordSetProperty->setValue($this->table, $this->mockRecordSet);
        
        Phake::when($this->mockRecordSet)->next()->thenReturn(true);
        Phake::when($this->mockFieldSet)->clear()->thenReturnSelf();
        Phake::when($this->mockFieldSet)->mapRecord->thenReturnSelf();
        
        $result = $this->table->next();
        
        self::assertTrue($result);
        Phake::verify($this->mockRecordSet)->next();
        Phake::verify($this->mockFieldSet)->clear();
        Phake::verify($this->mockFieldSet)->mapRecord(self::anything(), self::anything(), anything());
    }

    public function testHasActiveRecord_delegatesToRecordSet(): void
    {
        // Set recordSet using reflection
        $reflection = new \ReflectionClass($this->table);
        $recordSetProperty = $reflection->getProperty('recordSet');
        $recordSetProperty->setAccessible(true);
        $recordSetProperty->setValue($this->table, $this->mockRecordSet);
        
        Phake::when($this->mockRecordSet)->hasActiveRecord()->thenReturn(true);
        
        $result = $this->table->hasActiveRecord();
        
        self::assertTrue($result);
        Phake::verify($this->mockRecordSet)->hasActiveRecord();
    }

    public function testOperation_createsFieldOperationAndSetsOnQueryBuilder(): void
    {
        $mockFieldOperation = Phake::mock(FieldOperation::class);
        $mockField = Phake::mock(FieldDefinition::class);
        
        Phake::when($this->mockTableSchema)->getField('name')->thenReturn($mockField);
        Phake::when($this->mockQueryBuilder)->__set('name', $mockFieldOperation)->thenReturnSelf();
        
        $this->table->operation('name', Operation::Equals, 'John Doe');
        
        Phake::verify($this->mockTableSchema)->getField('name');
    }

    public function testCall_withValidOperation_delegatesToOperationMethod(): void
    {
        // Mock the operation method to verify it's called
        $mockField = Phake::mock(FieldDefinition::class);
        
        Phake::when($this->mockTableSchema)->getField('name')->thenReturn($mockField);
        
        // Call a method that corresponds to a valid Operation (e.g., 'equals')
        $this->table->equals('name', 'John Doe');
        
        // Verify that the operation method was called with the correct parameters
        // We can't directly verify the __call method, but we can verify the side effects
        // by checking that the operation method would have been called
        Phake::verify($this->mockTableSchema)->getField('name');
    }

    public function testGetTableSchema_returnsTableSchema(): void
    {
        $result = $this->table->getTableSchema();
        
        self::assertSame($this->mockTableSchema, $result);
    }

    public function testGetDatabase_returnsDatabase(): void
    {
        $result = $this->table->getDatabase();
        
        self::assertSame($this->mockDatabase, $result);
    }

    public function testGetFieldSet_returnsFieldSet(): void
    {
        $result = $this->table->getFieldSet();
        
        self::assertSame($this->mockFieldSet, $result);
    }

    public function testGetName_returnsTableName(): void
    {
        $result = $this->table->getName();
        
        self::assertEquals('test_table', $result);
    }
} 