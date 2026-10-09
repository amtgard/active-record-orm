<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Cache\InMemoryCache;
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
use Tests\Util\Constants;

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

        Phake::when($this->mockDatabase)->execute(Phake::anyParameters())->thenReturnCallback(function () {
            return $this->describeRecordSet();
        });
    }

    private function describeRecordSet(): RecordSet
    {
        $statement = Phake::mock(\PDOStatement::class);
        $recordSet = new RecordSet\PdoRecordSet($statement);
        $columns = json_decode(Constants::$DESCRIBE_TABLE_INTEG, true);
        $fetch = Phake::when($statement)->fetch();
        foreach ($columns as $column) {
            $fetch = $fetch->thenReturn($column);
        }
        $fetch->thenReturn(false);
        return $recordSet;
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
        $policy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache(InMemoryCache::builder()->build())
            ->build();

        $firstResult = $policy->applyTableSchemaPolicy("test_table");
        self::assertInstanceOf(UncachedTableSchema::class, $firstResult);

        $secondResult = $policy->applyTableSchemaPolicy("test_table");

        self::assertInstanceOf(FromJsonTableSchema::class, $secondResult);
        self::assertSame($firstResult->getTableName(), $secondResult->getTableName());
        self::assertSame(count($firstResult->getFields()), count($secondResult->getFields()));
        self::assertSame($firstResult->getPrimaryKey()->getName(), $secondResult->getPrimaryKey()->getName());
        self::assertSame($firstResult->getField('id')->getType(), $secondResult->getField('id')->getType());
        self::assertSame($firstResult->getField('id')->getExtra(), $secondResult->getField('id')->getExtra());
        self::assertSame($firstResult->getField('id')->getNullable(), $secondResult->getField('id')->getNullable());
        self::assertSame($firstResult->getField('string_value')->getNullable(), $secondResult->getField('string_value')->getNullable());
        self::assertSame('auto_increment', $secondResult->getField('id')->getExtra());
        self::assertFalse($secondResult->getField('id')->getNullable());
        self::assertTrue($secondResult->getField('string_value')->getNullable());
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
    }

    public function testApplyTableSchemaPolicy_hydratesTheRequestedTableName(): void
    {
        $cache = InMemoryCache::builder()->build();
        $policy = CachedDataAccessPolicy::builder()
            ->database($this->mockDatabase)
            ->cache($cache)
            ->build();
        $policy->applyTableSchemaPolicy('test_table');
        $stored = json_decode($cache->get('amtgard_orm_table_schema_test_table'), true, 512, JSON_THROW_ON_ERROR);
        $stored['table'] = 'other_table';
        $cache->set('amtgard_orm_table_schema_test_table', json_encode($stored, JSON_THROW_ON_ERROR));

        $hydrated = $policy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(FromJsonTableSchema::class, $hydrated);
        self::assertSame('test_table', $hydrated->getTableName());
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
    }

    public function testApplyTableSchemaPolicy_whenDocumentIsTruncated_describesAgain(): void
    {
        Phake::when($this->mockCache)->get('amtgard_orm_table_schema_test_table')->thenReturn('{');

        $result = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertSame('id', $result->getPrimaryKey()->getName());
        self::assertSame('auto_increment', $result->getField('id')->getExtra());
        Phake::verify($this->mockDatabase)->execute('describe test_table');
        Phake::verify($this->mockCache)->set('amtgard_orm_table_schema_test_table', Phake::capture($stored));
        $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('test_table', $decoded['table']);
        self::assertSame('id', $decoded['primaryKey']);
        self::assertArrayNotHasKey('database', $decoded);
        self::assertArrayNotHasKey('value', $decoded['fields'][0]);
    }

    public function testApplyTableSchemaPolicy_whenCachedValueIsNotAString_describesAgain(): void
    {
        Phake::when($this->mockCache)->get(Phake::anyParameters())->thenReturn(['fields' => []]);

        $result = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertSame('id', $result->getPrimaryKey()->getName());
        Phake::verify($this->mockDatabase)->execute('describe test_table');
        Phake::verify($this->mockCache)->set('amtgard_orm_table_schema_test_table', Phake::capture($stored));
        self::assertIsString($stored);
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
        
        Phake::verify($this->mockCache)->get($customKey);
        Phake::verify($this->mockCache)->set($customKey, Phake::capture($stored));
        self::assertInstanceOf(UncachedTableSchema::class, $result);
        $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($tableName, $decoded['table']);
        self::assertArrayNotHasKey('database', $decoded);
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