<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class UncachedDataAccessPolicyTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private Query $mockQuery;
    private UncachedDataAccessPolicy $dataAccessPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockConfiguration = Phake::mock(ActiveRecordOrmConfiguration::class);
        $this->mockQuery = Phake::mock(Query::class);
        $this->dataAccessPolicy = UncachedDataAccessPolicy::builder()->database($this->mockDatabase)->build();
    }

    public function testConstructor_createsInstanceSuccessfully(): void
    {
        $database = Phake::mock(Database::class);
        $configuration = Phake::mock(ActiveRecordOrmConfiguration::class);
        
        $policy = UncachedDataAccessPolicy::builder()->database($database)->build();
        
        self::assertInstanceOf(UncachedDataAccessPolicy::class, $policy);
    }

    public function testApplyTableSchemaPolicy_returnsUncachedTableSchema(): void
    {
        $tableName = 'test_table';
        
        $result = $this->dataAccessPolicy->applyTableSchemaPolicy($tableName);
        
        self::assertInstanceOf(UncachedTableSchema::class, $result);
        self::assertInstanceOf(TableSchema::class, $result);
    }

    public function testApplyQueryPolicy_executesQueryAndCallsPostQuery(): void
    {
        $mockRecordSet = Phake::mock(RecordSet::class);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($mockRecordSet);
        
        $result = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);
        
        Phake::verify($this->mockDatabase)->executeQuery($this->mockQuery);
        Phake::verify($this->mockQuery)->postQuery();
        self::assertEquals($mockRecordSet, $result);
    }

    public function testApplyQueryPolicy_returnsRecordSetFromDatabase(): void
    {
        $expectedRecordSet = Phake::mock(RecordSet::class);
        Phake::when($this->mockDatabase)->executeQuery($this->mockQuery)->thenReturn($expectedRecordSet);
        
        $result = $this->dataAccessPolicy->applyQueryPolicy($this->mockQuery);
        
        self::assertSame($expectedRecordSet, $result);
    }
} 