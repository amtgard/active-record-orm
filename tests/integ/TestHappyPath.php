<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Configuration\Database\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\TableFactory;
use Amtgard\ActiveRecordOrm\Configuration\TablePolicy\FileTablePolicyConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\TablePolicy\UncachedTablePolicy;
use Amtgard\ActiveRecordOrm\Interface\TablePolicy;
use Amtgard\ActiveRecordOrm\Interface\TablePolicyConfiguration;
use Amtgard\ActiveRecordOrm\Table;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;

class TestHappyPath extends TestCase
{
    private static Database $db;

    private static TableFactory $tableFactory;

    private static Table $itemTable;

    private static TablePolicyConfiguration $policyConfiguration;

    private static TablePolicy $tablePolicy;

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
        TestHappyPath::$db = Database::fromConfig($config);

        TestHappyPath::$policyConfiguration = new FileTablePolicyConfiguration();
        TestHappyPath::$tablePolicy = new UncachedTablePolicy(TestHappyPath::$db, TestHappyPath::$policyConfiguration);

        TestHappyPath::$itemTable = TableFactory::build(TestHappyPath::$db, TestHappyPath::$tablePolicy, 'item');
    }

    public function testFindItem() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->id = 1;
        if ($itemTable->find()) {
            assertEquals(1, $itemTable->find()->count());
            assertEquals(1, $itemTable->id);
        }
    }

    public function testFindItems() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->key = "1";
        if ($itemTable->find()) {
            assertEquals(2, $itemTable->find()->count());
            assertEquals(1, $itemTable->id);
        }
    }

    public function testInsertItems() {

    }

    public function testUpdateItems() {

    }

    public function testDeleteItems() {

    }

}