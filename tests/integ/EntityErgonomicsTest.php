<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Amtgard\ActiveRecordOrm\Trait\EntityTrait;
use Amtgard\PHPUnit\AmtgardTestCase;
use Amtgard\Traits\Builder\Builder;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;
use DateTime;
use Dotenv\Dotenv;
use function PHPUnit\Framework\assertEquals;

class SomeRepository extends EntityMapper implements EntityRepositoryInterface {
    static function getTableName() {
        return 'integ';
    }

    public static function getEntityClass() {
        return SomeEntity::class;
    }
}

class SomeEntity extends Entity {
    use Builder, ToBuilder, Data, EntityTrait;

    #[PrimaryKey]
    private int $id;
    #[Field('string_value')]
    private string $name;
    #[Field('datetime_value')]
    private DateTime $createdAt;
    private $linkId;
    #[Field('int_value', 'linkId')]
    private SomeEntity $link;
}

function SomeEntity(Entity $entity): SomeEntity {
    return SomeEntity::mapEntity($entity);
}

class EntityErgonomicsTest extends AmtgardTestCase
{
    private static Database $db;

    public static Table $itemTable;

    private static DataAccessPolicy $tablePolicy;

    public static EntityManager $em;

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
        EntityErgonomicsTest::$db = Database::fromProvider($provider);

        EntityErgonomicsTest::$tablePolicy = UncachedDataAccessPolicy::builder()->database(EntityErgonomicsTest::$db)->build();;

        EntityErgonomicsTest::$itemTable = TableFactory::build(EntityErgonomicsTest::$db, EntityErgonomicsTest::$tablePolicy, 'integ');

        EntityErgonomicsTest::$em = EntityManager::builder()
            ->database(EntityErgonomicsTest::$db)
            ->dataAccessPolicy(EntityErgonomicsTest::$tablePolicy)
            ->repositoryPolicy(UncachedPolicy::builder()->build())
            ->build();

        EntityManager::configure(EntityErgonomicsTest::$em);

        self::resetTable();

    }

    private static function resetTable()
    {
        EntityErgonomicsTest::$db->clear();
        EntityErgonomicsTest::$db->execute("truncate table integ");

        EntityErgonomicsTest::$db->clear();
        EntityErgonomicsTest::$db->string_value = "2";
        EntityErgonomicsTest::$db->int_value = 3;
        EntityErgonomicsTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        EntityErgonomicsTest::$db->clear();
        EntityErgonomicsTest::$db->string_value = "3";
        EntityErgonomicsTest::$db->execute("insert into integ (string_value) values (:string_value)");

        EntityErgonomicsTest::$db->clear();
        EntityErgonomicsTest::$db->string_value = "4";
        EntityErgonomicsTest::$db->execute("insert into integ (string_value) values (:string_value)");

        EntityErgonomicsTest::$em->clearMapper("integ");
    }

    public function testErgonomics(): void {
        $someRepo = EntityManager::getManager()->getRepository(SomeRepository::class);
        $someEntity = $someRepo->fetch(1);
        assertEquals("2", $someEntity->getName());
    }

    public function testComposedEntities_haveCachedSemantics(): void {
        $someRepo = EntityManager::getManager()->getRepository(SomeRepository::class);
        $someEntity = $someRepo->fetch(1);
        assertEquals("2", $someEntity->getName());

        $someEntity->setName("new name");
        assertEquals("new name", $someEntity->getName());

        $nextEntity = $someRepo->fetch(1);
        assertEquals("new name", $nextEntity->getName());
    }

    public function testEntityConveniences(): void {
        $itemTable = EntityErgonomicsTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityErgonomicsTest::$em)->entityInterface(SomeEntity::class)->table($itemTable)->build();

        $entity1 = $entityMapper->fetch(1);
        $entity2 = $entityMapper->fetch(2);

        $someEntity1 = SomeEntity::mapEntity($entity1);
        $someEntity2 = SomeEntity::mapEntity($entity2);

        assertEquals($entity1->id, $someEntity1->getId());

        $someEntity1->setLink($someEntity2);
    }

    public function testLowerErgonomics() {

        $itemTable = EntityErgonomicsTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityErgonomicsTest::$em)->table($itemTable)->build();

        $entity = $entityMapper->fetch(1);

        $someEntity = SomeEntity::builder()
            ->entity($entity)
            ->createdAt(new \DateTime())
            ->name("banana")
            ->build();

        self::assertDoesNotThrow(fn() => EntityErgonomicsTest::$em->persist($entityMapper->getName(), $someEntity));
    }

    public function testEntityMapper_withEntityInterface() {
        $itemTable = EntityErgonomicsTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityErgonomicsTest::$em)->entityInterface(SomeEntity::class)->table($itemTable)->build();

        $someEntity = $entityMapper->fetch(1);

        assertEquals(get_class($someEntity), SomeEntity::class);
    }

    public function testFetch_Update_andPersist() {
        $itemTable = EntityErgonomicsTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityErgonomicsTest::$em)->table($itemTable)->build();

        $someEntity = SomeEntity($entityMapper->fetch(1));

        $someEntity->setName("rabba zabba");

        $someEntity->flush($entityMapper);

        $entity = $entityMapper->fetch(1);

        assertEquals("rabba zabba", $entity->string_value);
    }

}