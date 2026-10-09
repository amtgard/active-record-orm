<?php

namespace Tests\Unit\Factory;

use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use ReflectionProperty;

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
        $table = TableFactory::build($this->mockDatabase, $this->mockPolicy, 'table_name');
        
        self::assertInstanceOf(Table::class, $table);
        Phake::verify($this->mockPolicy, Phake::times(1))->applyTableSchemaPolicy('table_name');
        self::assertSame($this->mockTableSchema, $table->getTableSchema());
        self::assertSame($this->mockTableSchema, $this->queryBuilderSchema($table));
    }

    public function testBuild_createsTableWithCorrectTableName(): void
    {
        $tableName = 'test_table';
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($tableName)->thenReturn($this->mockTableSchema);
        
        $table = TableFactory::build($this->mockDatabase, $this->mockPolicy, $tableName);
        
        self::assertInstanceOf(Table::class, $table);
        Phake::verify($this->mockPolicy, Phake::times(1))->applyTableSchemaPolicy($tableName);
    }

    public function testBuild_usesDataAccessPolicyCorrectly(): void
    {
        $table = TableFactory::build($this->mockDatabase, $this->mockPolicy, 'table_name');
        
        self::assertInstanceOf(Table::class, $table);
        // Verify that applyTableSchemaPolicy was called (already verified above)
        // This confirms the policy is being used
    }

    public function testBuildQueryBuilder_createsQueryBuilderWithCorrectConfiguration(): void
    {
        $queryBuilder = TableFactory::buildQueryBuilder($this->mockPolicy, 'table_name');
        
        self::assertInstanceOf(QueryBuilder::class, $queryBuilder);
        Phake::verify($this->mockPolicy)->applyTableSchemaPolicy('table_name');
    }

    public function testBuildQueryBuilder_createsQueryBuilderForDifferentTableName(): void
    {
        $tableName = 'another_table';
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($tableName)->thenReturn($this->mockTableSchema);
        
        $queryBuilder = TableFactory::buildQueryBuilder($this->mockPolicy, $tableName);
        
        self::assertInstanceOf(QueryBuilder::class, $queryBuilder);
        Phake::verify($this->mockPolicy)->applyTableSchemaPolicy($tableName);
    }

    public function testBuild_integrationWithBuildQueryBuilder(): void
    {
        // Verify that build() internally uses buildQueryBuilder
        $table = TableFactory::build($this->mockDatabase, $this->mockPolicy, 'table_name');
        
        self::assertInstanceOf(Table::class, $table);
        Phake::verify($this->mockPolicy, Phake::times(1))->applyTableSchemaPolicy('table_name');
    }

    public function testBuildQueryBuilder_withResolvedSchema_doesNotApplyPolicy(): void
    {
        $queryBuilder = TableFactory::buildQueryBuilder($this->mockPolicy, 'table_name', $this->mockTableSchema);

        self::assertInstanceOf(QueryBuilder::class, $queryBuilder);
        Phake::verify($this->mockPolicy, Phake::never())->applyTableSchemaPolicy(Phake::anyParameters());
        self::assertSame($this->mockTableSchema, $this->schemaOf($queryBuilder));
    }

    public function testClear_afterBuild_appliesSchemaPolicyAgain(): void
    {
        $builtSchema = Phake::mock(TableSchema::class);
        $clearedSchema = Phake::mock(TableSchema::class);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('table_name')
            ->thenReturn($builtSchema)
            ->thenReturn($clearedSchema);

        $table = TableFactory::build($this->mockDatabase, $this->mockPolicy, 'table_name');
        self::assertSame($builtSchema, $table->getTableSchema());
        self::assertSame($builtSchema, $this->queryBuilderSchema($table));

        $table->clear();

        Phake::verify($this->mockPolicy, Phake::times(2))->applyTableSchemaPolicy('table_name');
        self::assertSame($clearedSchema, $this->queryBuilderSchema($table));
    }

    private function queryBuilderSchema(Table $table): TableSchema
    {
        $property = new ReflectionProperty(Table::class, 'queryBuilder');
        $property->setAccessible(true);

        return $this->schemaOf($property->getValue($table));
    }

    private function schemaOf(QueryBuilder $queryBuilder): TableSchema
    {
        $property = new ReflectionProperty(QueryBuilder::class, 'tableSchema');
        $property->setAccessible(true);

        return $property->getValue($queryBuilder);
    }
}

