<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;

class EntityMapperTest extends TestCase
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
        EntityMapperTest::$db = Database::fromProvider($provider);

        EntityMapperTest::$tablePolicy = UncachedDataAccessPolicy::builder()->database(EntityMapperTest::$db)->build();;

        EntityMapperTest::$itemTable = TableFactory::build(EntityMapperTest::$db, EntityMapperTest::$tablePolicy, 'integ');

        EntityMapperTest::$em = EntityManager::builder()
            ->database(EntityMapperTest::$db)
            ->dataAccessPolicy(EntityMapperTest::$tablePolicy)
            ->repositoryPolicy(UncachedPolicy::builder()->build())
            ->build();

        self::resetTable();

    }

    private static function resetTable()
    {
        EntityMapperTest::$db->clear();
        EntityMapperTest::$db->execute("truncate table integ");

        EntityMapperTest::$db->clear();
        EntityMapperTest::$db->string_value = "2";
        EntityMapperTest::$db->int_value = 3;
        EntityMapperTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        EntityMapperTest::$db->clear();
        EntityMapperTest::$db->string_value = "4";
        EntityMapperTest::$db->int_value = 5;
        EntityMapperTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        EntityMapperTest::$db->clear();
        EntityMapperTest::$db->string_value = "Bunny Rabbit Foo-Foo";
        EntityMapperTest::$db->int_value = 6;
        EntityMapperTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");
        EntityMapperTest::$db->clear();

        EntityMapperTest::$em->clearMapper("integ");
    }

    public function testGetEntity() {
        self::resetTable();

        $entityId = 1;
        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->id = $entityId;
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        assertEquals("2", $entityMapper->string_value);
        $entity = $entityMapper->getEntity();
        assertEquals("2", $entity->string_value);
        assertEquals(3, $entity->int_value);
        assertEquals($entityId, EntityMapperTest::$em->getMapperEntities('integ')[1]->id);
    }

    public function testSubsequentGetEntityReturnsLocalEntityWithChanges() {
        self::resetTable();

        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
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
        assertEquals("22", EntityMapperTest::$em->getMapperEntities('integ')[1]->string_value);

        $entityMapper->clear();
        $entityMapper->id = 1;
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        assertEquals("22", $entityMapper->string_value);
        $entity2 = $entityMapper->getEntity();
        assertEquals("22", EntityMapperTest::$em->getMapperEntities('integ')[1]->string_value);
        assertEquals(1, count(EntityMapperTest::$em->getMapperEntities('integ')));
        assertEquals("22", $entity2->string_value);
        assertEquals(3, $entity2->int_value);
    }

    public function testSqlReturnsEntities() {
        self::resetTable();

        $entityId = 1;
        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
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