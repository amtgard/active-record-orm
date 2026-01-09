<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;
use Dotenv\Dotenv;
use function PHPUnit\Framework\assertEquals;

trait FieldHiding
{
    protected string $textValue;

    public function getTextValue()
    {
        return $this->textValue;
    }

    public function setTextValue($value)
    {
        $this->textValue = $value;
    }

}

interface FieldHidingInterface
{
    public function getTextValue();

    public function setTextValue($value);
}

#[RepositoryOf("integ", SomeHiddenEntity::class)]
class SomeHiddenRepository extends Repository
{
    static function getTableName()
    {
        return 'integ';
    }

    public static function getEntityClass()
    {
        return SomeHiddenEntity::class;
    }
}

#[EntityOf(SomeHiddenRepository::class)]
class SomeHiddenEntity extends RepositoryEntity implements FieldHidingInterface
{
    use Builder, ToBuilder, Data, FieldHiding;

    #[PrimaryKey]
    private ?int $id;
    #[Field('string_value')]
    private ?string $name;

    #[Field('text_value')]
    protected string $textValue;

    #[Field('int_value')]
    protected bool $booleanValue;
}

class EntityRepositoryTest extends AmtgardTestCase
{
    public static Database $db;

    public static Table $itemTable;

    public static DataAccessPolicy $tablePolicy;

    public static EntityManager $em;

    public function testHideFieldsWithTraits()
    {
        $this->resetTable();

        $someRepo = EntityManager::getManager()->getRepository(SomeHiddenRepository::class);
        $someEntity = $someRepo->fetch(1);
        assertEquals("2", $someEntity->getName());
        assertEquals("text_value", $someEntity->getTextValue());
    }

    public function testIntBackedBoolean() {
        $this->resetTable();

        $someRepo = EntityManager::getManager()->getRepository(SomeHiddenRepository::class);
        $someEntity = $someRepo->fetch(1);
        $someEntity->booleanValue = false;
        self::assertDoesNotThrow(fn() => $someEntity->persist($someEntity->getMapper()));
    }

    public function testNewEntityByCreateEntity_withHiddenFields(): void
    {
        $this->resetTable();

        $someRepo = EntityManager::getManager()->getRepository(SomeHiddenRepository::class);

        $someEntity = $someRepo->newRepositoryEntity();
        $someEntity->setName("new entity 1");
        $someEntity->setTextValue("new text value");
        $someEntity = $someRepo->persist($someEntity);
        assertEquals(2, $someEntity->id);
        EntityManager::getManager()->persist($someEntity);

        EntityRepositoryTest::$itemTable->clear();
        EntityRepositoryTest::$itemTable->string_value = "new entity 1";
        self::assertGreaterThan(0, EntityRepositoryTest::$itemTable->find());
        self::assertTrue(EntityRepositoryTest::$itemTable->next());
        assertEquals(2, EntityRepositoryTest::$itemTable->id);
        assertEquals("new text value", EntityRepositoryTest::$itemTable->text_value);

        EntityRepositoryTest::$itemTable->clear();
        assertEquals(2, EntityRepositoryTest::$itemTable->find());
    }

    public function setUp(): void
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
        EntityRepositoryTest::$db = Database::fromProvider($provider);

        EntityRepositoryTest::$tablePolicy = UncachedDataAccessPolicy::builder()->database(EntityRepositoryTest::$db)->build();
        ;

        EntityRepositoryTest::$itemTable = TableFactory::build(EntityRepositoryTest::$db, EntityRepositoryTest::$tablePolicy, 'integ');

        EntityRepositoryTest::$em = EntityManager::builder()
            ->database(EntityRepositoryTest::$db)
            ->dataAccessPolicy(EntityRepositoryTest::$tablePolicy)
            ->repositoryPolicy(UncachedPolicy::builder()->build())
            ->build();

        EntityManager::configure(EntityRepositoryTest::$em, true);

        self::resetTable();

    }

    private static function resetTable()
    {
        EntityRepositoryTest::$db->clear();
        EntityRepositoryTest::$db->execute("truncate table integ");

        EntityRepositoryTest::$db->clear();
        EntityRepositoryTest::$db->string_value = "2";
        EntityRepositoryTest::$db->int_value = 3;
        EntityRepositoryTest::$db->text_value = "text_value";
        EntityRepositoryTest::$db->execute("insert into integ (string_value, int_value, text_value) values (:string_value, :int_value, :text_value)");

        EntityRepositoryTest::$em->clearMapper("integ");
    }
}