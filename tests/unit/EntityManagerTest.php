<?php

namespace Tests\Unit;

use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\RepositoryPolicy;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Interface\TableQueryInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Phake\Mock;

class EntityManagerTest extends AmtgardTestCase
{
    private EntityManager $entityManager;
    #[Mock]
    private Database $mockDatabase;
    #[Mock]
    private DataAccessPolicy $mockPolicy;
    #[Mock]
    private RepositoryPolicy $mockRepositoryPolicy;
    #[Mock]
    private Entity $mockEntity;
    #[Mock]
    private FieldDefinition $mockFieldDefinition;
    #[Mock]
    private EntityMapper $mockEntityMapper;

    protected function setUp(): void
    {
        parent::setUp();

        Phake::initAnnotations($this);

        // Reset static state before each test
        $this->resetEntityManagerStaticState();

        EntityManager::configure(EntityManager::builder()
            ->database($this->mockDatabase)
            ->repositoryPolicy($this->mockRepositoryPolicy)
            ->dataAccessPolicy($this->mockPolicy)
            ->database($this->mockDatabase)
            ->mapperSupplier(fn($name) => $this->mockEntityMapper)
            ->preventShutdown(true)
            ->build());
    }

    protected function tearDown(): void
    {
        // Clean up static state after each test
        $this->resetEntityManagerStaticState();
        parent::tearDown();
    }

    private function resetEntityManagerStaticState(): void
    {
        // Use reflection to reset the static instance
        $reflection = new \ReflectionClass(EntityManager::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
    }

    public function testConfigure_createsAndSetsInstance(): void
    {
        $instance = EntityManager::getManager();
        self::assertInstanceOf(EntityManager::class, $instance);
    }

    public function testGetManager_returnsConfiguredInstance(): void
    {
        $instance = EntityManager::getManager();
        self::assertSame($instance, EntityManager::getManager());
    }

    public function testFlushAll_callsFlushTableForAllMappersAndEntities(): void
    {
        Phake::when($this->mockEntity)->getPrimaryKey()->thenReturn($this->mockFieldDefinition);
        Phake::when($this->mockFieldDefinition)->getValue()->thenReturn(22);

        $instance = EntityManager::getManager();
        $instance->mappedEntity("test_table", $this->mockEntity);
        $instance->flushAll();

        // Verify that flushEntity was called with the correct parameters
        Phake::verify($this->mockRepositoryPolicy)->flushEntity($this->mockEntityMapper, $this->mockEntity);
    }

    public function testClearAll_callsClearForAllMappers(): void
    {
        Phake::when($this->mockEntity)->getPrimaryKey()->thenReturn($this->mockFieldDefinition);
        Phake::when($this->mockFieldDefinition)->getName()->thenReturn("id");
        Phake::when($this->mockFieldDefinition)->getValue()->thenReturn(22);
        Phake::when($this->mockEntityMapper)->mappedEntity("test_table", $this->mockEntity)->thenReturn($this->mockEntity);

        EntityManager::getManager()->mappedEntity("test_table", $this->mockEntity);

        $entities = EntityManager::getManager()->getMapperEntities("test_table");
        self::assertEquals(1, count($entities));

        EntityManager::getManager()->clearAll();
        $entities = EntityManager::getManager()->getMapperEntities("test_table");
        self::assertEquals(0, count($entities));
    }

    public function testGetMapper_withExistingMapper_returnsMapper(): void
    {
        Phake::when($this->mockEntity)->getPrimaryKey()->thenReturn($this->mockFieldDefinition);
        Phake::when($this->mockFieldDefinition)->getName()->thenReturn("id");
        Phake::when($this->mockFieldDefinition)->getValue()->thenReturn(22);
        Phake::when($this->mockEntityMapper)->mappedEntity("test_table", $this->mockEntity)->thenReturn($this->mockEntity);

        EntityManager::getManager()->mappedEntity("test_table", $this->mockEntity);

        $result = EntityManager::getManager()->getMappers();

        self::assertSame($this->mockEntityMapper, $result["test_table"]);
    }

    public function testGetMapper_withNonExistentMapper_returnsNull(): void
    {
        $result = EntityManager::getManager()->getMappers()['non_existent_table'];
        
        self::assertNull($result);
    }

    public function testGetEntity_withNonExistentEntity_returnsNull(): void
    {
        $this->entityManager = EntityManager::builder()
            ->database($this->mockDatabase)
            ->dataAccessPolicy($this->mockPolicy)
            ->repositoryPolicy($this->mockRepositoryPolicy)
            ->build();
        
        $result = $this->entityManager->getEntity('test_table', 999);
        
        self::assertNull($result);
    }

}

