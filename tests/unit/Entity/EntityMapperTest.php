<?php

namespace Tests\Unit\Entity;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\Policy\RepositoryPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\assertEquals;

class EntityMapperTest extends AmtgardTestCase
{
    private EntityMapper $entityMapper;
    private Table $mockTable;
    private Database $mockDatabase;
    private EntityManager $mockEntityManager;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;
    private FieldDefinition $mockPrimaryKey;
    private RecordSet $mockRecordSet;
    private ResultSet $mockResultSet;
    private Entity $mockEntity;
    private DataAccessPolicy $mockPolicy;
    private RepositoryPolicy $mockRepositoryPolicy;
    private FieldDefinition $mockFieldDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockTable = Phake::mock(Table::class);
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        $this->mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $this->mockRecordSet = Phake::mock(RecordSet::class);
        $this->mockResultSet = Phake::mock(ResultSet::class);
        $this->mockPolicy = Phake::mock(DataAccessPolicy::class);
        $this->mockRepositoryPolicy = Phake::mock(RepositoryPolicy::class);
        $this->mockEntity = Phake::mock(Entity::class);
        $this->mockFieldDefinition = Phake::mock(FieldDefinition::class);

        // Mock table schema
        Phake::when($this->mockTableSchema)->getPrimaryKey()->thenReturn($this->mockPrimaryKey);
        Phake::when($this->mockPrimaryKey)->getName()->thenReturn('id');

        // Mock table methods
        Phake::when($this->mockTable)->getTableSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockTable)->getFieldSet()->thenReturn($this->mockFieldSet);
        Phake::when($this->mockTable)->getDatabase()->thenReturn($this->mockDatabase);
        Phake::when($this->mockTable)->getName()->thenReturn('test_table');
        Phake::when($this->mockTable)->getResultSet()->thenReturn($this->mockResultSet);

        // ResultSet methods
        Phake::when($this->mockResultSet)->getFieldMap()->thenReturn([
            'id' => '22'
        ]);

        // Mock database methods
        Phake::when($this->mockDatabase)->execute('SELECT * FROM test_table')->thenReturn($this->mockRecordSet);
        Phake::when($this->mockRecordSet)->size()->thenReturn(5);

        Phake::when($this->mockEntity)->getPrimaryKey()->thenReturn($this->mockFieldDefinition);
        Phake::when($this->mockFieldDefinition)->getValue()->thenReturn(22);

        $this->entityMapper = EntityMapper::builder()
            ->table($this->mockTable)
            ->database($this->mockDatabase)
            ->build();

        EntityManager::configure(EntityManager::builder()
            ->database($this->mockDatabase)
            ->repositoryPolicy($this->mockRepositoryPolicy)
            ->dataAccessPolicy($this->mockPolicy)
            ->database($this->mockDatabase)
            ->mapperSupplier(fn($name) => $this->entityMapper)
            ->preventShutdown(true)
            ->build(), true);
    }

    public function testGetEntity_inTableMode_returnsMappedEntity(): void
    {
        $result = $this->entityMapper->getEntity();

        self::assertInstanceOf(Entity::class, $result);
        assertEquals(22, $result->id);
        Phake::verify($this->mockTable)->getResultSet();
    }

    public function testGetEntity_inQueryMode_returnsMappedEntity(): void
    {
        $this->entityMapper = EntityMapper::builder()
            ->table($this->mockTable)
            ->database($this->mockDatabase)
            ->entityResultSetBuilder(fn() => $this->mockResultSet)
            ->build();

        // Set query mode
        $this->entityMapper->query('SELECT * FROM test_table');
        $this->entityMapper->execute();

        $result = $this->entityMapper->getEntity();

        self::assertInstanceOf(Entity::class, $result);
        assertEquals(22, $result->id);
    }

    public function testQuery_setsQueryModeAndSql(): void
    {
        $sql = 'SELECT * FROM users WHERE id = 1';

        $this->entityMapper->query($sql);

        // Use reflection to verify the query mode and SQL are set
        $reflection = new \ReflectionClass($this->entityMapper);
        $modeProperty = $reflection->getProperty('mode');
        $modeProperty->setAccessible(true);
        $querySqlProperty = $reflection->getProperty('querySql');
        $querySqlProperty->setAccessible(true);

        self::assertEquals('query', $modeProperty->getValue($this->entityMapper));
        self::assertEquals($sql, $querySqlProperty->getValue($this->entityMapper));
    }

    public function testExecute_executesQueryAndReturnsRecordCount(): void
    {
        $this->entityMapper->query('SELECT * FROM test_table');

        $result = $this->entityMapper->execute();

        self::assertEquals(5, $result);
        Phake::verify($this->mockDatabase)->execute('SELECT * FROM test_table');
        Phake::verify($this->mockRecordSet)->size();
    }

    public function testClear_resetsStateAndClearsTable(): void
    {
        $this->entityMapper->clear();

        // Use reflection to verify the state is reset
        $reflection = new \ReflectionClass($this->entityMapper);
        $modeProperty = $reflection->getProperty('mode');
        $modeProperty->setAccessible(true);
        $recordSetProperty = $reflection->getProperty('recordSet');
        $recordSetProperty->setAccessible(true);

        self::assertEquals('table', $modeProperty->getValue($this->entityMapper));
        self::assertNull($recordSetProperty->getValue($this->entityMapper));

        Phake::verify($this->mockTable)->clear();
        Phake::verify($this->mockDatabase)->clear();
    }

    public function testOrderBy_delegatesToTable(): void
    {
        $this->entityMapper->orderBy('name', OrderBy::ASC);

        Phake::verify($this->mockTable)->orderBy('name', OrderBy::ASC);
    }

    public function testSelect_delegatesToTable(): void
    {
        $fields = ['name', 'email'];
        $this->entityMapper->select($fields);

        Phake::verify($this->mockTable)->select($fields);
    }

    public function testFind_delegatesToTable(): void
    {
        Phake::when($this->mockTable)->find()->thenReturn(10);

        $result = $this->entityMapper->find();

        self::assertEquals(10, $result);
        Phake::verify($this->mockTable)->find();
    }

    public function testCount_delegatesToTable(): void
    {
        Phake::when($this->mockTable)->count('row_count')->thenReturn(15);

        $result = $this->entityMapper->count('row_count');

        self::assertEquals(15, $result);
        Phake::verify($this->mockTable)->count('row_count');
    }

    public function testPage_delegatesToTable(): void
    {
        $mockTableQuery = Phake::mock(ActiveRecordTableInterface::class);
        Phake::when($this->mockTable)->page(20, 2)->thenReturn($mockTableQuery);

        $result = $this->entityMapper->page(20, 2);

        self::assertSame($mockTableQuery, $result);
        Phake::verify($this->mockTable)->page(20, 2);
    }

    public function testLimit_delegatesToTable(): void
    {
        $this->entityMapper->limit(10, 20);

        Phake::verify($this->mockTable)->limit(10, 20);
    }

    public function testSize_delegatesToTable(): void
    {
        Phake::when($this->mockTable)->size()->thenReturn(25);

        $result = $this->entityMapper->size();

        self::assertEquals(25, $result);
        Phake::verify($this->mockTable)->size();
    }

    public function testNext_inTableMode_delegatesToTable(): void
    {
        Phake::when($this->mockTable)->next()->thenReturn(true);

        $result = $this->entityMapper->next();

        self::assertTrue($result);
        Phake::verify($this->mockTable)->next();
    }

    public function testNext_inQueryMode_delegatesToRecordSet(): void
    {
        // Set query mode and record set
        $this->entityMapper->query('SELECT * FROM test_table');
        $this->entityMapper->execute();

        Phake::when($this->mockRecordSet)->next()->thenReturn(true);

        $result = $this->entityMapper->next();

        self::assertTrue($result);
        Phake::verify($this->mockRecordSet)->next();
    }

    public function testHasActiveRecord_delegatesToTable(): void
    {
        Phake::when($this->mockTable)->hasActiveRecord()->thenReturn(true);

        $result = $this->entityMapper->hasActiveRecord();

        self::assertTrue($result);
        Phake::verify($this->mockTable)->hasActiveRecord();
    }

}
