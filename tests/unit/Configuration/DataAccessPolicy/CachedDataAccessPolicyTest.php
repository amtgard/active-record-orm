<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\CachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\RecordSet\InMemoryRecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Psr\SimpleCache\CacheInterface;
use Tests\util\Constants;

class CachedDataAccessPolicyTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private Query $mockQuery;
    private CachedDataAccessPolicy $dataAccessPolicy;
    private CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockQuery = Phake::mock(Query::class);
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockCache = Phake::mock(CacheInterface::class);
        $this->dataAccessPolicy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
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
        Phake::when($this->mockCache)->get(Phake::anyParameters())
            ->thenReturn(null);

        $tableName = 'test_table';

        $result = $this->dataAccessPolicy->applyTableSchemaPolicy($tableName);

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertInstanceOf(TableSchema::class, $result);
    }

    public function testApplyTableSchemaPolicy_whenCached_returnsFromJsonTableSchema(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())
            ->thenReturn(null)
            ->thenReturn(Constants::$JSON_ENCODED_INTEG_SCHEMA);

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

    public function testApplyTableSchemaPolicy_withCustomSchemaKeyNameSupplier_usesCustomKey(): void
    {
        $tableName = 'custom_table';
        $customKey = 'custom_schema_key_' . $tableName;
        
        // Create a custom schema key name supplier function
        $schemaKeyNameSupplier = function($tableName) {
            return 'custom_schema_key_' . $tableName;
        };
        
        // Build the policy with the custom schema key name supplier
        $dataAccessPolicy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($this->mockCache)
            ->schemaKeyNameSupplier($schemaKeyNameSupplier)
            ->build();
        
        // Mock the cache to return null (cache miss)
        Phake::when($this->mockCache)->get($customKey)->thenReturn(null);
        
        // Call the method that uses callSchemaKeyNameSupplier
        $result = $dataAccessPolicy->applyTableSchemaPolicy($tableName);
        
        // Verify that the cache was called with the custom key
        Phake::verify($this->mockCache)->get($customKey);
        
        // Verify that the result is an UncachedTableSchema (since cache returned null)
        self::assertInstanceOf(UncachedTableSchema::class, $result);
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

        Phake::when($this->mockCache)->get(Phake::anyParameters())
            ->thenReturn(null)
            ->thenReturn(Constants::$PDO_RECORD_SET_JSON);

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