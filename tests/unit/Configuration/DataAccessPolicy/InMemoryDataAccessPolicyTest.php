<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\InMemoryDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use PHPUnit\Framework\TestCase;

class InMemoryDataAccessPolicyTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private Query $mockQuery;
    private InMemoryDataAccessPolicy $dataAccessPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockQuery = Phake::mock(Query::class);
        $this->dataAccessPolicy = new InMemoryDataAccessPolicy($this->mockDatabase);
    }

    public function testConstructor(): void
    {
        $database = Phake::mock(Database::class);
        $policy = new InMemoryDataAccessPolicy($database);
        
        // Test that the object is created successfully
        self::assertInstanceOf(InMemoryDataAccessPolicy::class, $policy);
    }
}