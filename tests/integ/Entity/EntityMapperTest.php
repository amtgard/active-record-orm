<?php

namespace Tests\Integration\Entity;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Factory\EntityFactory;
use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Dotenv\Dotenv;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertGreaterThan;
use function PHPUnit\Framework\assertNotNull;

class EntityMapperTest extends AmtgardTestCase
{
    private static Database $db;

    public static Table $itemTable;

    private static DataAccessPolicy $tablePolicy;

    public static EntityManager $em;

    public static function setUpBeforeClass(): void
    {
        $dotenvPath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . "test-resources";
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
        EntityMapperTest::$db->boolean_value = true;
        EntityMapperTest::$db->execute("insert into integ (string_value, int_value, boolean_value) values (:string_value, :int_value, :boolean_value)");

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

    public function testGetEntityWithBoolean_returnsBooleanValue() {
        self::resetTable();

        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->string_value = "4";
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        assertEquals(true, $entityMapper->boolean_value);
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

    public function testCreateEntity() {
        self::resetTable();

        $itemTable = EntityMapperTest::$itemTable;
        /** @var EntityMapper $entityMapper */
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->string_value = "bar-baz";
        $entityMapper->int_value = 83;
        $entity = EntityFactory::build($entityMapper);
        $entity = $entityMapper->persist($entity);

        assertEquals("bar-baz", $entity->string_value);
        assertEquals(83, $entity->int_value);
        self::assertGreaterThan(0, $entity->id);

        $entityMapper->clear();
        $entityMapper->id = $entity->id;
        assertEquals(1, $entityMapper->find());
        $entityMapper->next();
        $fetched = $entityMapper->getEntity();
        assertEquals("bar-baz", $fetched->string_value);
        assertEquals(83, $fetched->int_value);
        assertGreaterThan(0, $fetched->id);
        assertEquals($entity->id, $fetched->id);
    }

    public function testTableFields_withConvenienceObjects() {
        self::resetTable();

        $entityId = 1;
        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();

        $entity = $entityMapper->fetchBy('id', $entityId);

        $datetime = new \DateTime();

        $entity->datetime_value = $datetime;
        $entity->int_value = $datetime;
        self::assertDoesNotThrow(fn() => $entity->persist($entityMapper));

        $entity = $entityMapper->fetchBy('id', $entityId);

        assertEquals($datetime->format("Y-m-d H:i:s"), $entity->datetime_value);
        assertEquals($datetime->format("U"), $entity->int_value);
    }

    public function testReferenceEntities_areConverted() {
        self::resetTable();

        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();

        $entity1 = $entityMapper->fetchBy('id', 1);
        $entity2 = $entityMapper->fetchBy('id', 2);

        $entity1->int_value = $entity2;

        self::assertDoesNotThrow(fn() => $entity1->persist($entityMapper));

        $entity = $entityMapper->fetchBy('id', 1);
        assertEquals($entity2->id, $entity1->int_value);
    }

    public function testFetch() {
        self::resetTable();

        $entityId = 1;
        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->id = $entityId;

        $entity = $entityMapper->fetch();
        assertNotNull($entity);
        assertEquals("2", $entity->string_value);
    }

    public function testFetch_withPrimaryKey() {
        self::resetTable();

        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();

        $entity = $entityMapper->fetch(1);
        assertNotNull($entity);
        assertEquals("2", $entity->string_value);
    }

    public function testFetchBy() {
        self::resetTable();

        $entityId = 1;
        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();

        $entity = $entityMapper->fetchBy('id', $entityId);
        assertNotNull($entity);
        assertEquals("2", $entity->string_value);
    }

    public function testPersist() {
        self::resetTable();

        $itemTable = EntityMapperTest::$itemTable;
        /** @var EntityMapper $entityMapper */
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
        $entityMapper->clear();
        $entityMapper->string_value = "bar-baz";
        $entityMapper->int_value = 83;
        $entity = EntityFactory::build($entityMapper);
        $entity = $entityMapper->persist($entity);


        $entityMapper->clear();
        $entityMapper->id = $entity->id;
        assertEquals("bar-baz", $entity->string_value);

        EntityMapperTest::$db->clear();
        EntityMapperTest::$db->execute("select * from integ where string_value = 'bar-baz'");
        assertEquals(83, $entity->int_value);
    }

    public function testDelete() {
        self::resetTable();

        $itemTable = EntityMapperTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityMapperTest::$em)->table($itemTable)->build();
        
        $entity = $entityMapper->fetchBy('id', 1);
        $entityMapper->delete($entity);

        $entityMapper->clear();
        $entityMapper->id = 1;
        assertEquals(0, $entityMapper->find());
    }
}
