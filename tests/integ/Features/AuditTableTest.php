<?php

namespace Tests\Integration\Features;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Factory\AuditTableFactory;
use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Dotenv\Dotenv;

class AuditTableTest extends AmtgardTestCase
{
    private static Database $db;

    private static DataAccessPolicy $tablePolicy;

    private static EntityManager $em;

    private static Table $auditTable;

    private static Table $auditLog;

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
        AuditTableTest::$db = Database::fromProvider($provider);

        AuditTableTest::$tablePolicy = UncachedDataAccessPolicy::builder()->database(AuditTableTest::$db)->build();;

        AuditTableTest::$em = EntityManager::builder()
            ->database(AuditTableTest::$db)
            ->dataAccessPolicy(AuditTableTest::$tablePolicy)
            ->repositoryPolicy(UncachedPolicy::builder()->build())
            ->mapperSupplier(fn ($db, $policy, $tableName) => AuditTableFactory::auditMapperSupplier($db, $policy, $tableName))
            ->build();

        AuditTableTest::$auditTable = AuditTableFactory::build(AuditTableTest::$db, AuditTableTest::$tablePolicy, 'audit_source');

        AuditTableTest::$auditLog = TableFactory::build(AuditTableTest::$db, AuditTableTest::$tablePolicy, 'audit_source_audit_log');

        self::resetTable();
    }

    private static function resetTable()
    {
        AuditTableTest::$db->clear();
        AuditTableTest::$db->execute("truncate table audit_source");

        AuditTableTest::$db->clear();
        AuditTableTest::$db->execute("truncate table audit_source_audit_log");
    }

    public function testAuditTableInsert() {
        AuditTableTest::$auditTable->clear();
        AuditTableTest::$auditTable->int_value = 2;
        AuditTableTest::$auditTable->save();

        AuditTableTest::$auditLog->clear();
        self::assertEquals(1, AuditTableTest::$auditLog->find());
        self::assertTrue(AuditTableTest::$auditLog->next());
        self::assertEquals(2, AuditTableTest::$auditLog->int_value);
        self::assertStringContainsString("int_value", AuditTableTest::$auditLog->fields);
        self::assertEquals("insert", AuditTableTest::$auditLog->action);
    }

    public function testAuditTableUpdate() {
        self::resetTable();

        AuditTableTest::$auditTable->clear();
        AuditTableTest::$auditTable->int_value = 2;
        AuditTableTest::$auditTable->save();

        AuditTableTest::$auditTable->clear();
        AuditTableTest::$auditTable->int_value = 2;
        self::assertEquals(1, AuditTableTest::$auditTable->find());
        self::assertTrue(AuditTableTest::$auditTable->next());
        AuditTableTest::$auditTable->int_value = 4;
        AuditTableTest::$auditTable->save();

        AuditTableTest::$auditLog->clear();
        self::assertEquals(2, AuditTableTest::$auditLog->find());
        AuditTableTest::$auditLog->clear();
        AuditTableTest::$auditLog->int_value = 4;
        self::assertEquals(1, AuditTableTest::$auditLog->find());
        self::assertTrue(AuditTableTest::$auditLog->next());
        self::assertEquals(4, AuditTableTest::$auditLog->int_value);
        self::assertStringContainsString("int_value", AuditTableTest::$auditLog->fields);
        self::assertEquals("update", AuditTableTest::$auditLog->action);
    }

    public function testAuditTableDelete() {
        self::resetTable();

        AuditTableTest::$auditTable->clear();
        AuditTableTest::$auditTable->int_value = 2;
        AuditTableTest::$auditTable->save();

        AuditTableTest::$auditTable->clear();
        AuditTableTest::$auditTable->int_value = 2;
        self::assertEquals(1, AuditTableTest::$auditTable->find());
        self::assertTrue(AuditTableTest::$auditTable->next());
        AuditTableTest::$auditTable->delete();

        AuditTableTest::$auditLog->clear();
        self::assertEquals(2, AuditTableTest::$auditLog->find());
        AuditTableTest::$auditLog->clear();
        AuditTableTest::$auditLog->id = 2;
        self::assertEquals(1, AuditTableTest::$auditLog->find());
        self::assertTrue(AuditTableTest::$auditLog->next());
        self::assertEquals("delete", AuditTableTest::$auditLog->action);
    }

}