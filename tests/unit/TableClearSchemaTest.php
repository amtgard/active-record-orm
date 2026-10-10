<?php

namespace Tests\Unit;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

/**
 * Guards clear()/point-fetch behavior: each clear rebuilds the query builder and may re-resolve schema.
 */
class TableClearSchemaTest extends AmtgardTestCase
{
    public function testClear_rebuildsQueryBuilderWithoutPassingExistingTableSchema(): void
    {
        $database = Phake::mock(Database::class);
        $policy = Phake::mock(UncachedDataAccessPolicy::class);
        $tableSchema = Phake::mock(TableSchema::class);
        $firstQueryBuilder = Phake::mock(QueryBuilder::class);
        $secondQueryBuilder = Phake::mock(QueryBuilder::class);

        Phake::when($policy)->applyTableSchemaPolicy('integ')->thenReturn($tableSchema);
        Phake::when($firstQueryBuilder)->tableSchema->thenReturn($tableSchema);
        Phake::when($secondQueryBuilder)->tableSchema->thenReturn($tableSchema);

        $table = Table::builder()
            ->tableName('integ')
            ->database($database)
            ->tableSchema($tableSchema)
            ->dataAccessPolicy($policy)
            ->queryBuilder($firstQueryBuilder)
            ->fieldSet(FieldSet::builder()->build())
            ->build();

        $table->tableFactory = TableFactoryStub::class;
        TableFactoryStub::$nextQueryBuilder = $secondQueryBuilder;

        $table->clear();

        self::assertSame(1, TableFactoryStub::$buildQueryBuilderCalls);
        self::assertSame(
            $tableSchema,
            TableFactoryStub::$lastTableSchemaArgument,
            'clear() passes the existing TableSchema so uncached policy does not DESCRIBE again'
        );
    }

    protected function tearDown(): void
    {
        TableFactoryStub::$buildQueryBuilderCalls = 0;
        TableFactoryStub::$lastTableSchemaArgument = null;
        parent::tearDown();
    }
}

class TableFactoryStub extends TableFactory
{
    public static int $buildQueryBuilderCalls = 0;

    public static ?TableSchema $lastTableSchemaArgument = null;

    public static QueryBuilder $nextQueryBuilder;

    public static function buildQueryBuilder(
        $policy,
        string $tableName,
        ?TableSchema $tableSchema = null
    ): QueryBuilder {
        self::$buildQueryBuilderCalls++;
        self::$lastTableSchemaArgument = $tableSchema;

        return self::$nextQueryBuilder;
    }
}
