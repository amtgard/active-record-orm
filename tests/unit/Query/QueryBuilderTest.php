<?php

namespace Tests\Unit\Query;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\InsertExpression;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\InsertStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\UpdateStatement;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use PharIo\Manifest\Type;
use TypeError;
use function PHPUnit\Framework\any;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertEqualsIgnoringCase;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertNotEmpty;
use function PHPUnit\Framework\assertNotNull;

class QueryBuilderTest extends AmtgardTestCase
{
    private QueryBuilder $queryBuilder;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;
    private DataAccessPolicy $mockPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        $this->mockPolicy = Phake::mock(DataAccessPolicy::class);
        Phake::when($this->mockTableSchema)->getTableName->thenReturn('mock_table');
        
        $this->queryBuilder = QueryBuilder::builder()
            ->tableSchema($this->mockTableSchema)
            ->fieldSet($this->mockFieldSet)
            ->tablePolicy($this->mockPolicy)
            ->build();
    }

    public function testSet_withValidFieldOperation_setsFieldInFieldSet(): void
    {
        $mockFieldOperation = Phake::mock(FieldOperation::class);
        
        $this->queryBuilder->__set('name', $mockFieldOperation);
        
        Phake::verify($this->mockFieldSet)->setField($mockFieldOperation);
    }

    public function testSet_withInvalidFieldOperation_throwsException(): void
    {
        self::assertThrows(TypeError::class, function () {
            $this->queryBuilder->name = 'invalid_value';
        });
    }

    public function testSelect_withSingleFieldName_addsToFieldSelectors(): void
    {
        $this->queryBuilder->select('name');
        
        $reflection = new \ReflectionClass($this->queryBuilder);
        $fieldSelectorsProperty = $reflection->getProperty('fieldSelectors');
        $fieldSelectorsProperty->setAccessible(true);
        $fieldSelectors = $fieldSelectorsProperty->getValue($this->queryBuilder);
        
        self::assertArrayHasKey('name', $fieldSelectors);
        self::assertEquals('name', $fieldSelectors['name']);
    }

    public function testSelect_withArrayOfFieldNames_addsAllToFieldSelectors(): void
    {
        $this->queryBuilder->select(['name', 'email', 'age']);
        
        $reflection = new \ReflectionClass($this->queryBuilder);
        $fieldSelectorsProperty = $reflection->getProperty('fieldSelectors');
        $fieldSelectorsProperty->setAccessible(true);
        $fieldSelectors = $fieldSelectorsProperty->getValue($this->queryBuilder);
        
        self::assertArrayHasKey('name', $fieldSelectors);
        self::assertArrayHasKey('email', $fieldSelectors);
        self::assertArrayHasKey('age', $fieldSelectors);
        self::assertEquals('name', $fieldSelectors['name']);
        self::assertEquals('email', $fieldSelectors['email']);
        self::assertEquals('age', $fieldSelectors['age']);
    }

    public function testHash_returnsMd5Hash(): void
    {
        $result = $this->queryBuilder->hash();
        
        self::assertIsString($result);
        self::assertEquals(32, strlen($result));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result);
    }

    public function testHash_alwaysReturnsSameHashForEmptyString(): void
    {
        $hash1 = $this->queryBuilder->hash();
        $hash2 = $this->queryBuilder->hash();
        
        self::assertEquals($hash1, $hash2);
        self::assertEquals(md5(''), $hash1);
    }

    public function testCompile_returnsQueryWithStatementData(): void
    {
        $mockStatement = Phake::mock(Statement::class);
        $mockQuery = Phake::mock(Query::class);
        
        // Set up reflection to access protected statement property
        $reflection = new \ReflectionClass($this->queryBuilder);
        $statementProperty = $reflection->getProperty('statement');
        $statementProperty->setAccessible(true);
        $statementProperty->setValue($this->queryBuilder, $mockStatement);
        
        Phake::when($mockStatement)->buildSql()->thenReturn('SELECT * FROM users');
        Phake::when($mockStatement)->getStatementParams()->thenReturn(['id' => 123]);
        Phake::when($mockStatement)->getPostQueryCallback()->thenReturn(null);
        
        $result = $this->queryBuilder->compile();
        
        self::assertInstanceOf(Query::class, $result);
        Phake::verify($mockStatement)->buildSql();
        Phake::verify($mockStatement)->getStatementParams();
        Phake::verify($mockStatement)->getPostQueryCallback();
    }

    public function testCompile_withPostQueryCallback_includesCallbackInQuery(): void
    {
        $mockStatement = Phake::mock(Statement::class);
        $callback = fn() => true;
        
        // Set up reflection to access protected statement property
        $reflection = new \ReflectionClass($this->queryBuilder);
        $statementProperty = $reflection->getProperty('statement');
        $statementProperty->setAccessible(true);
        $statementProperty->setValue($this->queryBuilder, $mockStatement);
        
        Phake::when($mockStatement)->buildSql()->thenReturn('INSERT INTO users');
        Phake::when($mockStatement)->getStatementParams()->thenReturn([]);
        Phake::when($mockStatement)->getPostQueryCallback()->thenReturn($callback);

        $result = $this->queryBuilder->compile();
        
        Phake::verify($mockStatement)->getPostQueryCallback();
        assertNotEmpty($result->getPostQueryCallback());
    }

    public function testUpsert_withPrimaryKeySet_createsUpdateStatement(): void
    {
        $mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $mockFieldOperation = Phake::mock(FieldOperation::class);

        Phake::when($this->mockTableSchema)->primaryKeyIsSet->thenReturn(true);
        Phake::when($this->mockTableSchema)->getPrimaryKey()->thenReturn($mockPrimaryKey);
        Phake::when($mockPrimaryKey)->getName()->thenReturn('id');
        Phake::when($this->mockFieldSet)->getField('id')->thenReturn($mockFieldOperation);
        Phake::when($mockFieldOperation)->value->thenReturn(123);
        Phake::when($mockFieldOperation)->getValue()->thenReturn(123);
        Phake::when($mockFieldOperation)->getOperation()->thenReturn(Operation::Equals);
        
        $result = $this->queryBuilder->upsert();
        
        self::assertSame($this->queryBuilder, $result);
        Phake::verify($this->mockFieldSet)->setFieldOperation($this->mockTableSchema, 'id', Operation::Equals);;
        $reflection = new \ReflectionClass($result);
        $statementProperty = $reflection->getProperty('statement');
        $statementProperty->setAccessible(true);
        assertInstanceOf(UpdateStatement::class, $statementProperty->getValue($result));
    }

    public function testUpsert_withoutPrimaryKeySet_createsInsertStatement(): void
    {
        $this->mockPrimaryKey();
        
        $callback = fn() => true;
        $result = $this->queryBuilder->upsert($callback);

        Phake::verify($this->mockFieldSet, Phake::times(0))->setFieldOperation(any(), any(), Operation::Equals);
        $reflection = new \ReflectionClass($result);
        $statementProperty = $reflection->getProperty('statement');
        $statementProperty->setAccessible(true);
        assertInstanceOf(InsertStatement::class, $statementProperty->getValue($result));
    }

    public function testDelete_withPrimaryKeySet_createsDeleteStatement(): void
    {
        $this->mockPrimaryKey();
        $mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $mockFieldOperation = Phake::mock(FieldOperation::class);
        Phake::when($mockPrimaryKey)->getName()->thenReturn('id');

        Phake::when($mockFieldOperation)->getField()->thenReturn($mockPrimaryKey);
        Phake::when($mockFieldOperation)->getOperation()->thenReturn(Operation::Equals);
        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike])
            ->thenReturn([$mockFieldOperation]);

        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::In, Operation::NotIn])
            ->thenReturn([]);

        Phake::when($this->mockFieldSet)->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull])
            ->thenReturn([]);

        $result = $this->queryBuilder->delete();

        $query = $result->compile();

        $sql = $query->getSql();

        assertEqualsIgnoringCase("DELETE FROM mock_table where id = :id", trim($sql));
        Phake::verify($this->mockTableSchema, Phake::times(3))->getPrimaryKey();
    }

    public function testDelete_withoutPrimaryKeySet_createsDeleteStatement(): void
    {
        $mockPrimaryKey = Phake::mock(FieldDefinition::class);
        Phake::when($this->mockTableSchema)->getPrimaryKey()->thenReturn($mockPrimaryKey);
        Phake::when($mockPrimaryKey)->getName()->thenReturn('id');
        Phake::when($this->mockFieldSet)->getFieldsByOperation->thenReturn([]);

        $result = $this->queryBuilder->delete();

        $query = $result->compile();

        $sql = $query->getSql();

        assertEqualsIgnoringCase("DELETE FROM mock_table", trim($sql));
        Phake::verify($this->mockTableSchema, Phake::times(2))->getPrimaryKey();
    }

    public function testFind_createsFindStatementWithAllOptions(): void
    {
        $this->mockPrimaryKey();

        $this->queryBuilder->limit(10, 20);
        $this->queryBuilder->orderBy('name', OrderBy::ASC);
        
        $result = $this->queryBuilder->find();
        
        self::assertSame($this->queryBuilder, $result);
        Phake::verify($this->mockFieldSet)->updateSetOperationToEquals();
    }

    public function testFind_withoutOptions_createsBasicFindStatement(): void
    {
        $mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $mockFieldOperation = Phake::mock(FieldOperation::class);

        Phake::when($this->mockTableSchema)->getPrimaryKey()->thenReturn($mockPrimaryKey);
        Phake::when($mockPrimaryKey)->getName()->thenReturn('id');
        Phake::when($this->mockFieldSet)->getField('id')->thenReturn($mockFieldOperation);

        $result = $this->queryBuilder->find();
        
        self::assertSame($this->queryBuilder, $result);
        Phake::verify($this->mockFieldSet)->updateSetOperationToEquals();
    }

    public function testOrderBy_addsOrderByOperation(): void
    {
        $this->queryBuilder->orderBy('name', OrderBy::ASC);
        $this->queryBuilder->orderBy('email', OrderBy::DESC);
        
        $reflection = new \ReflectionClass($this->queryBuilder);
        $orderByOperationsProperty = $reflection->getProperty('orderByOperations');
        $orderByOperationsProperty->setAccessible(true);
        $orderByOperations = $orderByOperationsProperty->getValue($this->queryBuilder);
        
        self::assertEquals(OrderBy::ASC, $orderByOperations['name']);
        self::assertEquals(OrderBy::DESC, $orderByOperations['email']);
    }

    public function testOrderBy_overwritesExistingOrderByOperation(): void
    {
        $this->queryBuilder->orderBy('name', OrderBy::ASC);
        $this->queryBuilder->orderBy('name', OrderBy::DESC);
        
        $reflection = new \ReflectionClass($this->queryBuilder);
        $orderByOperationsProperty = $reflection->getProperty('orderByOperations');
        $orderByOperationsProperty->setAccessible(true);
        $orderByOperations = $orderByOperationsProperty->getValue($this->queryBuilder);
        
        self::assertEquals(OrderBy::DESC, $orderByOperations['name']);
    }

    public function testLimit_setsLimitOptions(): void
    {
        $result = $this->queryBuilder->limit(10, 20);
        
        self::assertSame($this->queryBuilder, $result);
        
        $reflection = new \ReflectionClass($this->queryBuilder);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $offsetProperty = $reflection->getProperty('offset');
        $rowCountProperty = $reflection->getProperty('rowCount');
        
        $withLimitProperty->setAccessible(true);
        $offsetProperty->setAccessible(true);
        $rowCountProperty->setAccessible(true);
        
        self::assertTrue($withLimitProperty->getValue($this->queryBuilder));
        self::assertEquals(10, $offsetProperty->getValue($this->queryBuilder));
        self::assertEquals(20, $rowCountProperty->getValue($this->queryBuilder));
    }

    public function testLimit_withDefaultParameters_setsDefaultLimitOptions(): void
    {
        $result = $this->queryBuilder->limit();
        
        self::assertSame($this->queryBuilder, $result);
        
        $reflection = new \ReflectionClass($this->queryBuilder);
        $withLimitProperty = $reflection->getProperty('withLimit');
        $offsetProperty = $reflection->getProperty('offset');
        $rowCountProperty = $reflection->getProperty('rowCount');
        
        $withLimitProperty->setAccessible(true);
        $offsetProperty->setAccessible(true);
        $rowCountProperty->setAccessible(true);
        
        self::assertTrue($withLimitProperty->getValue($this->queryBuilder));
        self::assertEquals(10, $offsetProperty->getValue($this->queryBuilder));
        self::assertNull($rowCountProperty->getValue($this->queryBuilder));
    }

    public function testCount_createsCountStatementWithDefaultAlias(): void
    {
        $this->mockPrimaryKey();

        $result = $this->queryBuilder->count();
        
        self::assertSame($this->queryBuilder, $result);
        Phake::verify($this->mockFieldSet)->updateSetOperationToEquals();
    }

    public function testCount_createsCountStatementWithCustomAlias(): void
    {
        $this->mockPrimaryKey();

        $result = $this->queryBuilder->count('custom_count');
        
        self::assertSame($this->queryBuilder, $result);
        Phake::verify($this->mockFieldSet)->updateSetOperationToEquals();
    }

    private function mockPrimaryKey() {
        $mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $mockFieldOperation = Phake::mock(FieldOperation::class);
        Phake::when($mockFieldOperation)->getOperation()->thenReturn(Operation::Equals);

        Phake::when($this->mockTableSchema)->getPrimaryKey()->thenReturn($mockPrimaryKey);
        Phake::when($mockPrimaryKey)->getName()->thenReturn('id');
        Phake::when($this->mockFieldSet)->getField('id')->thenReturn($mockFieldOperation);
    }
}