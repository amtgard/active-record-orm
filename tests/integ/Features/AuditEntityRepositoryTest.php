<?php
namespace Tests\Integration\Features;

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
use Amtgard\ActiveRecordOrm\Feature\Entity\Repository\AuditRepositoryEntityTrait;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;
use Dotenv\Dotenv;
use function PHPUnit\Framework\assertNotNull;

#[RepositoryOf("audit_source", AuditSourceEntity::class)]
class AuditSourceRepository extends Repository {
    static function getTableName() {
        return 'audit_source';
    }

    public static function getEntityClass() {
        return AuditSourceEntity::class;
    }
}

#[EntityOf(AuditSourceRepository::class)]
class AuditSourceEntity extends RepositoryEntity {
    use Builder, ToBuilder, Data, AuditRepositoryEntityTrait;

    #[PrimaryKey]
    private ?int $id;
    #[Field('string_value')]
    private ?string $name;
    #[Field('int_value')]
    private int $number;
}

class AuditEntityRepositoryTest extends AmtgardTestCase
{
    public static Database $db;

    public static DataAccessPolicy $tablePolicy;

    public static EntityManager $em;

    private static Table $auditLog;


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
        AuditEntityRepositoryTest::$db = Database::fromProvider($provider);

        AuditEntityRepositoryTest::$tablePolicy = UncachedDataAccessPolicy::builder()->database(AuditEntityRepositoryTest::$db)->build();

        AuditEntityRepositoryTest::$em = EntityManager::builder()
            ->database(AuditEntityRepositoryTest::$db)
            ->dataAccessPolicy(AuditEntityRepositoryTest::$tablePolicy)
            ->repositoryPolicy(UncachedPolicy::builder()->build())
            ->build();

        EntityManager::configure(AuditEntityRepositoryTest::$em, true);

        AuditEntityRepositoryTest::$auditLog = TableFactory::build(AuditEntityRepositoryTest::$db, AuditEntityRepositoryTest::$tablePolicy, 'audit_source_audit_log');

        self::resetTable();

    }

    private static function resetTable()
    {
        AuditEntityRepositoryTest::$db->clear();
        AuditEntityRepositoryTest::$db->execute("truncate table audit_source");

        AuditEntityRepositoryTest::$db->clear();
        AuditEntityRepositoryTest::$db->execute("truncate table audit_source_audit_log");
    }

    public function testAuditTableInsert() {
        $auditEntity = AuditSourceEntity::builder()->name("new entity 2")->number(100)->build();
        EntityManager::getManager()->persist($auditEntity);
        assertNotNull($auditEntity->getId());

        AuditEntityRepositoryTest::$auditLog->clear();
        self::assertEquals(1, AuditEntityRepositoryTest::$auditLog->find());
        self::assertTrue(AuditEntityRepositoryTest::$auditLog->next());
        self::assertEquals(100, AuditEntityRepositoryTest::$auditLog->int_value);
        self::assertStringContainsString("int_value", AuditEntityRepositoryTest::$auditLog->fields);
        self::assertEquals("new entity 2", AuditEntityRepositoryTest::$auditLog->string_value);
        self::assertStringContainsString("string_value", AuditEntityRepositoryTest::$auditLog->fields);
        self::assertEquals("insert", AuditEntityRepositoryTest::$auditLog->action);
    }
}