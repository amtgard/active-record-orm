<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Amtgard\ActiveRecordOrm\Trait\RepositoryEntityTrait;
use Amtgard\PHPUnit\AmtgardTestCase;
use Amtgard\Traits\Builder\Builder;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;
use DateTime;
use Dotenv\Dotenv;
use function PHPUnit\Framework\assertDoesNotMatchRegularExpression;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNull;

#[RepositoryOf("integ", SomeEntity::class)]
class SomeRepository extends Repository {
    static function getTableName() {
        return 'integ';
    }

    public static function getEntityClass() {
        return SomeEntity::class;
    }
}

#[EntityOf(SomeRepository::class)]
class SomeEntity extends RepositoryEntity {
    use Builder, ToBuilder, Data, RepositoryEntityTrait;

    #[PrimaryKey]
    private ?int $id;
    #[Field('string_value')]
    private ?string $name;
    #[Field('datetime_value')]
    private ?DateTime $createdAt;
    private ?int $linkId;
    #[Field('int_value', 'linkId')]
    private ?SomeEntity $link;
}

function SomeEntity(EntityInterface $entity): SomeEntity {
    return SomeEntity::toRepositoryEntity($entity);
}

class EntityErgonomicsTest extends AmtgardTestCase
{
    public static Database $db;

    public static Table $itemTable;

    public static DataAccessPolicy $tablePolicy;

    public static EntityManager $em;

    public function setUp(): void
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

    public function testFetchWithLocalFieldName() {
        $someRepo = EntityManager::getManager()->getRepository(SomeRepository::class);

        $missingEntity = $someRepo->fetchBy("name", "2");

        assertEquals(3, $missingEntity->getLinkId());
    }

    public function testFetchByIsMissing(): void {
        $someRepo = EntityManager::getManager()->getRepository(SomeRepository::class);

        $missingEntity = $someRepo->fetchBy("name", "string_value");

        assertNull($missingEntity);
    }

    public function testNewEntityByCreateEntity(): void {
        $someRepo = EntityManager::getManager()->getRepository(SomeRepository::class);

        $someEntity = $someRepo->createEntity();
        $someEntity->setName("new entity 1");
        assertEquals(4, $someEntity->id);
        EntityManager::getManager()->persist($someEntity);

        EntityErgonomicsTest::$itemTable->clear();
        EntityErgonomicsTest::$itemTable->string_value = "new entity 1";
        self::assertGreaterThan(0, EntityErgonomicsTest::$itemTable->find());
        self::assertTrue(EntityErgonomicsTest::$itemTable->next());
        assertEquals(4, EntityErgonomicsTest::$itemTable->id);

        EntityErgonomicsTest::$itemTable->clear();
        assertEquals(4, EntityErgonomicsTest::$itemTable->find());
    }

    public function testNewEntityViaErgonomicRepository(): void {
        $someEntity = SomeEntity::builder()->name("new entity 2")->build();
        EntityManager::getManager()->persist($someEntity);

        EntityErgonomicsTest::$itemTable->clear();
        EntityErgonomicsTest::$itemTable->string_value = "new entity 2";
        self::assertGreaterThan(0, EntityErgonomicsTest::$itemTable->find());
        self::assertTrue(EntityErgonomicsTest::$itemTable->next());
        assertEquals(4, EntityErgonomicsTest::$itemTable->id);

        EntityErgonomicsTest::$itemTable->clear();
        assertEquals(4, EntityErgonomicsTest::$itemTable->find());
    }

    public function testEntityBuilderPattern_SelfRegistersRepository(): void {
        self::assertDoesNotThrow(fn() => SomeEntity::builder()->name("new entity 2")->build());
    }

    public function testCreateEntitySetsPrimaryKeyId(): void {
        $someEntity = SomeEntity::builder()->name("new entity 2")->build();
        EntityManager::getManager()->persist($someEntity);

        assertEquals(4, $someEntity->id);
        assertEquals("new entity 2", $someEntity->name);
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

    public function testLowerErgonomics() {

        $itemTable = EntityErgonomicsTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityErgonomicsTest::$em)->table($itemTable)->build();

        $entity = $entityMapper->fetch(1);

        $someEntity = SomeEntity::builder()
            ->entity($entity)
            ->createdAt(new \DateTime())
            ->name("banana")
            ->build();

        self::assertDoesNotThrow(fn() => EntityErgonomicsTest::$em->register($entityMapper->getName(), $someEntity));
    }

    public function testFetch_Update_andPersist() {
        $itemTable = EntityErgonomicsTest::$itemTable;
        $entityMapper = EntityMapper::builder()->em(EntityErgonomicsTest::$em)->table($itemTable)->build();

        $entity = $entityMapper->fetch(1);
        $someEntity = SomeEntity($entity);

        $someEntity->setName("rabba zabba");

        $someEntity->flush($entityMapper);

        $entity = $entityMapper->fetch(1);

        assertEquals("rabba zabba", $entity->string_value);
    }

}