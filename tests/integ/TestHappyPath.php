<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Impl\Database;
use Amtgard\ActiveRecordOrm\Implementation\Mysql\MysqlIDatabase;
use Amtgard\ActiveRecordOrm\Implementation\Mysql\MysqlEnvIDatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Interface\Database\IDatabase;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;

class TestHappyPath extends TestCase
{
    private static Database $db;

    private static TableFactory $tableFactory;

    private static ITable $itemTable;

    private static TableDefinitionCache $tableDefinitionCache;

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

        $config = MysqlEnvIDatabaseConfiguration::fromEnvironment();
        TestHappyPath::$db = new Database(MysqlIDatabase::fromConfig($config));

        $policy = TableDefinitionPolicy::fromConfig($config);
        TestHappyPath::$tableDefinitionCache = new TableDefinitionCache(TestHappyPath::$db);

        TestHappyPath::$tableFactory = new TableFactory(new MysqlTableFactory(TestHappyPath::$db, TestHappyPath::$tableDefinitionCache));

        TestHappyPath::$tableDefinitionCache->clearCache('item');
        TestHappyPath::$itemTable = TestHappyPath::$tableFactory->createTable('item');
    }

    public function testFindItem() {
        TestHappyPath::$tableDefinitionCache->setSession('session-id');
        $itemTable = TestHappyPath::$tableFactory->createTable('item');
        $itemTable->id = 1;
        if ($itemTable->find()) {
            assertEquals(1, $itemTable->find()->count());
            assertEquals(1, $itemTable->id);
        }
    }

    public function testFindItems() {
        TestHappyPath::$tableDefinitionCache->setSession('session-id');
        $itemTable = TestHappyPath::$tableFactory->createTable('item');
        $itemTable->key = "1";
        if ($itemTable->find()) {
            assertEquals(2, $itemTable->find()->count());
            assertEquals(1, $itemTable->id);
        }
    }

    public function testInsertItems() {

    }

    public function testUpsertItems() {

    }

    public function testDeleteItems() {

    }

}