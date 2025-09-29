<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;

class EntityManagerTest extends TestCase
{
    private static Database $db;

    private static Table $itemTable;

    private static DataAccessPolicy $tablePolicy;

    private static EntityManager $em;

    public static function setUpBeforeClass(): void
    {
        $dotenvPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "test-resources";
        $dotenvFile = $dotenvPath . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($dotenvFile)) {
            $dotenv = Dotenv::createImmutable($dotenvPath);
            $dotenv->safeLoad();
        } else {
            exit('Dotenv file not found in ' . $dotenvPath);
        }

        $config = DatabaseConfiguration::fromEnvironment();
        $provider = MysqlPdoProvider::fromConfiguration($config);
        EntityManagerTest::$db = Database::fromProvider($provider);

        EntityManagerTest::$tablePolicy = UncachedDataAccessPolicy::builder()->database(EntityManagerTest::$db)->build();;

        EntityManagerTest::$itemTable = TableFactory::build(EntityManagerTest::$db, EntityManagerTest::$tablePolicy, 'integ');

        EntityManagerTest::$em = EntityManager::builder()
            ->database(EntityManagerTest::$db)
            ->dataAccessPolicy(EntityManagerTest::$tablePolicy)
            ->repositoryPolicy(UncachedPolicy::builder()->build())
            ->build();

        self::resetTable();

    }

    private static function resetTable()
    {
        EntityManagerTest::$db->clear();
        EntityManagerTest::$db->execute("truncate table integ");

        EntityManagerTest::$db->clear();
        EntityManagerTest::$db->string_value = "2";
        EntityManagerTest::$db->int_value = 3;
        EntityManagerTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        EntityManagerTest::$db->clear();
        EntityManagerTest::$db->string_value = "4";
        EntityManagerTest::$db->int_value = 5;
        EntityManagerTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        EntityManagerTest::$db->clear();
        EntityManagerTest::$db->string_value = "Bunny Rabbit Foo-Foo";
        EntityManagerTest::$db->int_value = 6;
        EntityManagerTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");
        EntityManagerTest::$db->clear();

        EntityManagerTest::$em->clearMapper("integ");
    }

    public function testGetEntity_withSingleton() {
        self::resetTable();

        $entityId = 1;
        EntityManager::configure(EntityManagerTest::$em);

        $itemTable = EntityManagerTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityManager::getManager())->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->id = $entityId;
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        assertEquals("2", $entityMapper->string_value);
        $entity = $entityMapper->getEntity();
        assertEquals("2", $entity->string_value);
        assertEquals(3, $entity->int_value);
        assertEquals($entityId, EntityManager::getManager()->getMapperEntities('integ')[1]->id);
    }

    public function testGetEntity() {
        self::resetTable();

        $entityId = 1;
        $itemTable = EntityManagerTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityManagerTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->id = $entityId;
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        assertEquals("2", $entityMapper->string_value);
        $entity = $entityMapper->getEntity();
        assertEquals("2", $entity->string_value);
        assertEquals(3, $entity->int_value);
        assertEquals($entityId, EntityManagerTest::$em->getMapperEntities('integ')[1]->id);
    }

    public function testSubsequentGetEntityReturnsLocalEntityWithChanges() {
        self::resetTable();

        $itemTable = EntityManagerTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityManagerTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->id = 1;
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        assertEquals("2", $entityMapper->string_value);
        $entity1 = $entityMapper->getEntity();
        assertEquals("2", $entity1->string_value);
        $entity1->string_value = "22";
        assertEquals("22", $entity1->string_value);
        assertEquals(3, $entity1->int_value);
        assertEquals("22", EntityManagerTest::$em->getMapperEntities('integ')[1]->string_value);

        $entityMapper->clear();
        $entityMapper->id = 1;
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        assertEquals("22", $entityMapper->string_value);
        $entity2 = $entityMapper->getEntity();
        assertEquals("22", EntityManagerTest::$em->getMapperEntities('integ')[1]->string_value);
        assertEquals(1, count(EntityManagerTest::$em->getMapperEntities('integ')));
        assertEquals("22", $entity2->string_value);
        assertEquals(3, $entity2->int_value);
    }

    public function testSqlReturnsEntities() {
        self::resetTable();

        $entityId = 1;
        $itemTable = EntityManagerTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityManagerTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->query("select * from integ where id = :id");
        $entityMapper->id = $entityId;
        $entityMapper->execute();
        $entityMapper->next();
        $entity = $entityMapper->getEntity();
        assertEquals("2", $entity->string_value);
        assertEquals(3, $entity->int_value);
    }
}