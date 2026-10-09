<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\InMemoryDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Tests\Util\Constants;

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
        Phake::when($this->mockDatabase)->getDatabaseName()->thenReturn('testdb');
        $this->dataAccessPolicy = InMemoryDataAccessPolicy::builder()
            ->database($this->mockDatabase)
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
        $tableName = 'test_table';
        
        $result = $this->dataAccessPolicy->applyTableSchemaPolicy($tableName);
        
        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertInstanceOf(TableSchema::class, $result);
    }

    public function testApplyTableSchemaPolicy_memoizesTheBuiltSchema(): void
    {
        $firstResult = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');
        $secondResult = $this->dataAccessPolicy->applyTableSchemaPolicy('test_table');

        self::assertInstanceOf(UncachedTableSchema::class, $firstResult);
        self::assertSame($firstResult, $secondResult);
        self::assertSame('auto_increment', $secondResult->getField('id')->getExtra());
        self::assertFalse($secondResult->getField('number_enum')->getNullable());
        self::assertTrue($secondResult->getField('string_value')->getNullable());
        Phake::verify($this->mockDatabase, Phake::times(1))->execute('describe test_table');
        Phake::verify($this->mockDatabase, Phake::times(1))->clear();
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

    public function testApplyQueryPolicy_executesTheQueryAndReturnsTheDatabaseRecordSet(): void
    {
        $mockRecordSet = Phake::mock(RecordSet::class);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($mockRecordSet);

        $result = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);

        Phake::verify($this->mockDatabase)->executeQuery($this->mockQuery);
        Phake::verify($this->mockQuery)->postQuery();
        Phake::verify($this->mockQuery, Phake::never())->hash();
        self::assertSame($mockRecordSet, $result);
    }

    public function testApplyQueryPolicy_executesEveryQueryAgainstTheDatabase(): void
    {
        $firstSet = Phake::mock(RecordSet::class);
        $secondSet = Phake::mock(RecordSet::class);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)
            ->thenReturn($firstSet)
            ->thenReturn($secondSet);

        $first = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);
        $second = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);

        Phake::verify($this->mockDatabase, Phake::times(2))->executeQuery($this->mockQuery);
        Phake::verify($this->mockQuery, Phake::times(2))->postQuery();
        self::assertSame($firstSet, $first);
        self::assertSame($secondSet, $second);
    }

    public function testApplyQueryPolicy_withDifferentQueries_executesEachAgainstTheDatabase(): void
    {
        $mockRecordSet = Phake::mock(RecordSet::class);
        $query1 = Phake::mock(Query::class);
        $query2 = Phake::mock(Query::class);
        Phake::when($this->mockDatabase)->executeQuery($query1)->thenReturn($mockRecordSet);
        Phake::when($this->mockDatabase)->executeQuery($query2)->thenReturn($mockRecordSet);

        $result1 = $this->dataAccessPolicy->applyQueryPolicy($query1);
        $result2 = $this->dataAccessPolicy->applyQueryPolicy($query2);

        Phake::verify($this->mockDatabase)->executeQuery($query1);
        Phake::verify($this->mockDatabase)->executeQuery($query2);
        Phake::verify($query1)->postQuery();
        Phake::verify($query2)->postQuery();
        self::assertSame($mockRecordSet, $result1);
        self::assertSame($mockRecordSet, $result2);
    }
}