<?php

namespace Tests\Unit;

use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\assertNotNull;

class TableFactoryTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private DataAccessPolicy $mockPolicy;
    private TableSchema $mockTableSchema;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockPolicy = Phake::mock(DataAccessPolicy::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);

        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('table_name')->thenReturn($this->mockTableSchema);
    }

    public function testBuild_createsTableWithCorrectConfiguration(): void
    {
        self::assertDoesNotThrow(function() {
            $table = TableFactory::build($this->mockDatabase, $this->mockPolicy, 'table_name');
            self::assertInstanceOf(Table::class, $table);
        });
    }

    public function testBuildQueryBuilder_createsQueryBuilderWithCorrectConfiguration(): void
    {
        self::assertDoesNotThrow(function() {
           $query = TableFactory::buildQueryBuilder($this->mockPolicy, 'table_name');
           self::assertInstanceOf(QueryBuilder::class, $query);
        });
    }
} 