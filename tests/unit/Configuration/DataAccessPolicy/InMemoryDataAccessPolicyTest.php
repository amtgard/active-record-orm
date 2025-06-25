<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\InMemoryDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\RecordSet\InMemoryRecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Tests\util\Constants;

class InMemoryDataAccessPolicyTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private Query $mockQuery;
    private InMemoryDataAccessPolicy $dataAccessPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockQuery = Phake::mock(Query::class);
        $this->mockDatabase = Phake::mock(Database::class);
        $this->dataAccessPolicy = InMemoryDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->build();

        $__statement = Phake::mock(\PDOStatement::class);
        $recordSet = new RecordSet\PdoRecordSet($__statement);

        $integ_schema = json_decode(Constants::$DESCRIBE_TABLE_INTEG, true);
        $phakeWhenRef = Phake::when($__statement)->fetch();
        foreach ($integ_schema as $column) {
            $phakeWhenRef = $phakeWhenRef->thenReturn($column);
        }
        $phakeWhenRef->thenReturn(false);

        Phake::when($this->mockDatabase)->execute("describe integ")->thenReturn($recordSet);
    }

    // Tests for applyTableSchemaPolicy method
    public function testApplyTableSchemaPolicy_whenNotCached_createsUncachedTableSchema(): void
    {
        $tableName = 'test_table';
        
        $result = $this->dataAccessPolicy->applyTableSchemaPolicy($tableName);
        
        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertInstanceOf(TableSchema::class, $result);
    }

    public function testApplyTableSchemaPolicy_whenCached_returnsFromJsonTableSchema(): void
    {

        // First call - should cache an UncachedTableSchema
        $firstResult = $this->dataAccessPolicy->applyTableSchemaPolicy("integ");
        self::assertInstanceOf(UncachedTableSchema::class, $firstResult);
        
        // Second call - should return the cached schema
        $secondResult = $this->dataAccessPolicy->applyTableSchemaPolicy("integ");

        self::assertInstanceOf(FromJsonTableSchema::class, $secondResult);
        self::assertEquals($firstResult->getTableName(), $secondResult->getTableName());
        self::assertEquals(count($firstResult->getFields()), count($secondResult->getFields()));
        self::assertEquals($firstResult->getPrimaryKey()->getName(), $secondResult->getPrimaryKey()->getName());
    }

    public function testApplyTableSchemaPolicy_withDifferentTableNames_createsSeparateCaches(): void
    {
        $tableName1 = 'table_one';
        $tableName2 = 'table_two';
        
        $result1 = $this->dataAccessPolicy->applyTableSchemaPolicy($tableName1);
        $result2 = $this->dataAccessPolicy->applyTableSchemaPolicy($tableName2);
        
        self::assertInstanceOf(UncachedTableSchema::class, $result1);
        self::assertInstanceOf(UncachedTableSchema::class, $result2);
        self::assertNotSame($result1, $result2);
    }

    // Tests for applyQueryPolicy method
    public function testApplyQueryPolicy_whenNotCached_executesQueryAndCachesResult(): void
    {
        $queryHash = 'test_query_hash';
        $mockRecordSet = Phake::mock(RecordSet::class);
        $recordSetVars = [
            'records' => [],
            'pdoDefinition' => [],
            'fieldDefinition' => []
        ];
        
        Phake::when($this->mockQuery)->hash()->thenReturn($queryHash);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($mockRecordSet);
        Phake::when($mockRecordSet)->jsonSerialize()->thenReturn($recordSetVars);
        
        $result = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);
        
        Phake::verify($this->mockDatabase)->executeQuery($this->mockQuery);
        Phake::verify($this->mockQuery)->hash();
        self::assertInstanceOf(InMemoryRecordSet::class, $result);
    }

    public function testApplyQueryPolicy_whenCached_returnsCachedResultWithoutDatabaseCall(): void
    {
        $queryHash = 'test_query_hash';
        $mockRecordSet = Phake::mock(RecordSet::class);
        $recordSetVars = [
            'records' => [],
            'pdoDefinition' => [],
            'fieldDefinition' => []
        ];

        Phake::when($this->mockQuery)->hash()->thenReturn($queryHash);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($mockRecordSet);
        Phake::when($mockRecordSet)->jsonSerialize()->thenReturn($recordSetVars);
        
        // First call - should execute query and cache
        $firstResult = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);
        
        // Second call - should return cached result without database call
        $secondResult = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);
        
        // Verify database was only called once (on first call)
        Phake::verify($this->mockDatabase, Phake::times(1))->executeQuery($this->mockQuery);
        self::assertInstanceOf(InMemoryRecordSet::class, $firstResult);
        self::assertInstanceOf(InMemoryRecordSet::class, $secondResult);
    }

    public function testApplyQueryPolicy_withDifferentQueryHashes_createsSeparateCaches(): void
    {
        $queryHash1 = 'hash_one';
        $mockRecordSet = Phake::mock(RecordSet::class);
        $recordSetVars = [
            'records' => [],
            'pdoDefinition' => [],
            'fieldDefinition' => []
        ];

        Phake::when($this->mockQuery)->hash()->thenReturn($queryHash1);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($mockRecordSet);
        Phake::when($mockRecordSet)->jsonSerialize()->thenReturn($recordSetVars);

        $queryHash2 = 'hash_two';

        // Create two different queries
        $query1 = Phake::mock(Query::class);
        $query2 = Phake::mock(Query::class);
        
        Phake::when($query1)->hash()->thenReturn($queryHash1);
        Phake::when($query2)->hash()->thenReturn($queryHash2);
        Phake::when($this->mockDatabase)->executeQuery($query1)->thenReturn($mockRecordSet);
        Phake::when($this->mockDatabase)->executeQuery($query2)->thenReturn($mockRecordSet);
        
        $result1 = $this->dataAccessPolicy->applyQueryPolicy($query1);
        $result2 = $this->dataAccessPolicy->applyQueryPolicy($query2);
        
        // Both should execute database calls since they have different hashes
        Phake::verify($this->mockDatabase)->executeQuery($query1);
        Phake::verify($this->mockDatabase)->executeQuery($query2);
        self::assertInstanceOf(InMemoryRecordSet::class, $result1);
        self::assertInstanceOf(InMemoryRecordSet::class, $result2);
    }
}