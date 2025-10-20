<?php

namespace Tests\Unit\Trait;

use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\Trait\RepositoryTrait;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\assertEquals;

#[RepositoryOf("test_table", TestRepositoryEntity::class)]
class TestRepository extends \Amtgard\ActiveRecordOrm\Entity\Repository\Repository
{
    public static function getTableName()
    {
        return 'test_table';
    }

    public static function getEntityClass()
    {
        return TestRepositoryEntity::class;
    }
}

class TestRepositoryEntity extends \Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity
{
    public static function toRepositoryEntity(EntityInterface $entity): EntityInterface
    {
        return $entity;
    }
}

class RepositoryTraitTest extends AmtgardTestCase
{
    private EntityMapper $mockEntityMapper;
    private EntityManager $mockEntityManager;
    private Table $mockTable;
    private Database $mockDatabase;
    private TableSchema $mockTableSchema;
    private Entity $mockEntity;
    private FieldDefinition $mockPrimaryKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEntityMapper = Phake::mock(EntityMapper::class);
        $this->mockEntityManager = Phake::mock(EntityManager::class);
        $this->mockTable = Phake::mock(Table::class);
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockEntity = Phake::mock(Entity::class);
        $this->mockPrimaryKey = Phake::mock(FieldDefinition::class);

        Phake::when($this->mockTable)->getName()->thenReturn('test_table');
        Phake::when($this->mockEntityMapper)->getName()->thenReturn('test_table');
        Phake::when($this->mockEntity)->getPrimaryKey()->thenReturn($this->mockPrimaryKey);
        Phake::when($this->mockPrimaryKey)->getValue()->thenReturn(1);
        Phake::when($this->mockEntity)->getInternalEntity()->thenReturn($this->mockEntity);
    }

    public function testPostInit_initializesRepositoryOf(): void
    {
        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $reflection = new \ReflectionClass($repository);
        $repositoryEntityClassProperty = $reflection->getProperty('repositoryEntityClass');
        $repositoryEntityClassProperty->setAccessible(true);
        $tableNameProperty = $reflection->getProperty('tableName');
        $tableNameProperty->setAccessible(true);

        self::assertEquals(TestRepositoryEntity::class, $repositoryEntityClassProperty->getValue($repository));
        self::assertEquals('test_table', $tableNameProperty->getValue($repository));
    }

    public function testSet_delegatesToEntityMapper(): void
    {
        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $repository->testField = 'test_value';

        Phake::verify($this->mockEntityMapper)->__set('testField', 'test_value');
    }

    public function testGet_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->__get('testField')->thenReturn('test_value');

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->testField;

        assertEquals('test_value', $result);
    }

    public function testClear_delegatesToEntityMapper(): void
    {
        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $repository->clear();

        Phake::verify($this->mockEntityMapper)->clear();
    }

    public function testOrderBy_delegatesToEntityMapper(): void
    {
        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $repository->orderBy('name', OrderBy::ASC);

        Phake::verify($this->mockEntityMapper)->orderBy('name', OrderBy::ASC);
    }

    public function testSelect_delegatesToEntityMapper(): void
    {
        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $fields = ['name', 'email'];
        $repository->select($fields);

        Phake::verify($this->mockEntityMapper)->select($fields);
    }

    public function testFind_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->find()->thenReturn(10);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->find();

        assertEquals(10, $result);
    }

    public function testCount_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->count('row_count')->thenReturn(15);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->count('row_count');

        assertEquals(15, $result);
    }

    public function testPage_delegatesToEntityMapper(): void
    {
        $mockTableQuery = Phake::mock(ActiveRecordTableInterface::class);
        Phake::when($this->mockEntityMapper)->page(2, 20)->thenReturn($mockTableQuery);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->page(20, 2);

        self::assertSame($mockTableQuery, $result);
    }

    public function testLimit_delegatesToEntityMapper(): void
    {
        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $repository->limit(10, 20);

        Phake::verify($this->mockEntityMapper)->limit(10, 20);
    }

    public function testSize_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->size()->thenReturn(25);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->size();

        assertEquals(25, $result);
    }

    public function testNext_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->next()->thenReturn(true);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->next();

        self::assertTrue($result);
    }

    public function testHasActiveRecord_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->hasActiveRecord()->thenReturn(true);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->hasActiveRecord();

        self::assertTrue($result);
    }

    public function testGetEntity_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->getEntity()->thenReturn($this->mockEntity);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->getEntity();

        self::assertSame($this->mockEntity, $result);
    }

    public function testFetch_convertsToRepositoryEntity(): void
    {
        Phake::when($this->mockEntityMapper)->fetch(1)->thenReturn($this->mockEntity);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->fetch(1);

        self::assertInstanceOf(EntityInterface::class, $result);
        Phake::verify($this->mockEntityMapper)->fetch(1);
    }

    public function testFetchBy_convertsToRepositoryEntity(): void
    {
        Phake::when($this->mockEntityMapper)->fetchBy('name', 'test')->thenReturn($this->mockEntity);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->fetchBy('name', 'test');

        self::assertInstanceOf(EntityInterface::class, $result);
        Phake::verify($this->mockEntityMapper)->fetchBy('name', 'test');
    }

    public function testPersist_convertsToRepositoryEntity(): void
    {
        $mockRepositoryEntity = Phake::mock(TestRepositoryEntity::class);
        Phake::when($mockRepositoryEntity)->getInternalEntity()->thenReturn($this->mockEntity);
        Phake::when($this->mockEntityMapper)->persist($this->mockEntity)->thenReturn($this->mockEntity);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->persist($mockRepositoryEntity);

        self::assertInstanceOf(EntityInterface::class, $result);
        Phake::verify($this->mockEntityMapper)->persist($this->mockEntity);
    }

    public function testCreateEntity_convertsToRepositoryEntity(): void
    {
        Phake::when($this->mockEntityMapper)->createEntity()->thenReturn($this->mockEntity);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->createEntity();

        self::assertInstanceOf(EntityInterface::class, $result);
        Phake::verify($this->mockEntityMapper)->clear();
        Phake::verify($this->mockEntityMapper)->createEntity();
    }

    public function testCreateMapperEntity_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->createInternalEntity()->thenReturn($this->mockEntity);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->createMapperEntity();

        self::assertSame($this->mockEntity, $result);
        Phake::verify($this->mockEntityMapper)->clear();
        Phake::verify($this->mockEntityMapper)->createInternalEntity();
    }

    public function testCreateInternalEntity_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->createInternalEntity()->thenReturn($this->mockEntity);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->createInternalEntity();

        self::assertSame($this->mockEntity, $result);
        Phake::verify($this->mockEntityMapper)->clear();
        Phake::verify($this->mockEntityMapper)->createInternalEntity();
    }

    public function testQuery_delegatesToEntityMapper(): void
    {
        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $repository->query('SELECT * FROM test_table');

        Phake::verify($this->mockEntityMapper)->query('SELECT * FROM test_table');
    }

    public function testExecute_delegatesToEntityMapper(): void
    {
        Phake::when($this->mockEntityMapper)->execute()->thenReturn(5);

        $repository = TestRepository::builder()
            ->entityManager($this->mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($this->mockEntityMapper)
            ->build();

        $result = $repository->execute();

        assertEquals(5, $result);
    }
}
