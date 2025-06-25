<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\OrmConfiguration\FileBasedActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertGreaterThan;
use function PHPUnit\Framework\assertNotEqualsIgnoringCase;
use function PHPUnit\Framework\assertTrue;

class TestHappyPath extends TestCase
{
    private static Database $db;

    private static TableFactory $tableFactory;

    private static Table $itemTable;

    private static ActiveRecordOrmConfiguration $policyConfiguration;

    private static DataAccessPolicy $tablePolicy;

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

        TestHappyPath::$tablePolicy = UncachedDataAccessPolicy::builder()->database(TestHappyPath::$db)->build();;

        TestHappyPath::$itemTable = TableFactory::build(TestHappyPath::$db, TestHappyPath::$tablePolicy, 'integ');

        TestHappyPath::$db->clear();
        TestHappyPath::$db->execute("truncate table integ");

        TestHappyPath::$db->clear();
        TestHappyPath::$db->string_value = "2";
        TestHappyPath::$db->int_value = 3;
        TestHappyPath::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        TestHappyPath::$db->clear();
        TestHappyPath::$db->string_value = "4";
        TestHappyPath::$db->int_value = 5;
        TestHappyPath::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        TestHappyPath::$db->clear();
        TestHappyPath::$db->string_value = "Bunny Rabbit Foo-Foo";
        TestHappyPath::$db->int_value = 6;
        TestHappyPath::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");
        TestHappyPath::$db->clear();

    }

    public function testFindItemById() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->id = 1;
        assertEquals(1, $itemTable->find());
        $itemTable->next();
        assertEquals(1, $itemTable->size());
        assertEquals("2", $itemTable->string_value);
        assertEquals(3, $itemTable->int_value);
    }

    public function testFindAllItems() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        if ($itemTable->find()) {
            assertEquals(3, $itemTable->size());
            while ($itemTable->next()) {
                assertGreaterThan(0, $itemTable->id);
            }
            assertFalse($itemTable->next());
        }
    }

    public function testFindItemByComparison() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->greater('int_value', 3);
        if ($itemTable->find()) {
            assertEquals(2, $itemTable->size());
            while ($itemTable->next()) {
                assertGreaterThan(3, $itemTable->int_value);
            }
            assertFalse($itemTable->next());
        }
    }

    public function testFindLikeAnywaysFindItYeah() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->notLike('string_value', "bunny rabbit foo-foo");
        if ($itemTable->find()) {
            assertEquals(2, $itemTable->size());
            while ($itemTable->next()) {
                assertNotEqualsIgnoringCase("bunny rabbit foo-foo", $itemTable->string_value);
            }
            assertFalse($itemTable->next());
        }
    }

    public function testFindAMemberOf() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->in('int_value', [3, 5]);
        if ($itemTable->find()) {
            assertEquals(2, $itemTable->size());
            while ($itemTable->next()) {
                assertGreaterThan(2, $itemTable->int_value);
            }
            assertFalse($itemTable->next());
        }
    }

    public function testCountRecords() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        assertTrue($itemTable->count() == 1);
        assertEquals(3, $itemTable->row_count);
    }

    public function testPagination() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->page(2, 1);
        assertTrue($itemTable->find() == 1);
    }

    public function testLimit() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->limit(1);
        assertTrue($itemTable->find() < 3);
    }

    public function testLimitWithRowCount() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->limit(1, 1);
        assertTrue($itemTable->find() == 1);
    }

    public function testWhenUpdatingInsertingAndDeleting_IterateResultSet() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        assertTrue($itemTable->find() > 0);
        assertEquals(3, $itemTable->size());
        $resultSet = $itemTable->getResultSet();
        while ($resultSet->next()) {
            assertGreaterThan(0, $resultSet->id);

            // Update a record
            $itemTable->clear();
            $itemTable->string_value = bin2hex(openssl_random_pseudo_bytes(6));
            $itemTable->save();
            $itemKey = $itemTable->id;
            assertGreaterThan(0, $itemKey);

            // Insert
            $itemTable->clear();
            $itemTable->id = $itemKey;
            $itemTable->string_value = bin2hex(openssl_random_pseudo_bytes(6));
            $itemTable->save();

            $itemTable->delete();
        }
        assertFalse($resultSet->next());

        $itemTable->clear();
        assertTrue($itemTable->find() > 0);
        assertEquals(3, $itemTable->size());
    }

    public function testInsertItems() {
        $itemValue = "insert_item_test_" . bin2hex(openssl_random_pseudo_bytes(6));
        TestHappyPath::$db->clear();
        TestHappyPath::$db->execute("delete from integ where string_value like 'insert_item_test_%'");
        TestHappyPath::$db->clear();

        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->string_value = $itemValue;
        $itemTable->save();
        assertEquals($itemValue, $itemTable->string_value);
        assertGreaterThan(0, $itemTable->id);

        $itemTable->clear();
        $itemTable->string_value = $itemValue;
        self::assertTrue($itemTable->find() > 0);
        assertEquals(1, $itemTable->size());
        $itemTable->next();
        assertEquals($itemValue, $itemTable->string_value);
    }

    public function testUpdateItems() {
        $itemValue = "insert_item_test_" . bin2hex(openssl_random_pseudo_bytes(6));
        TestHappyPath::$db->clear();
        TestHappyPath::$db->execute("delete from integ where string_value like 'insert_item_test_%'");
        TestHappyPath::$db->clear();

        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->string_value = $itemValue;
        $itemTable->save();
        assertEquals($itemValue, $itemTable->string_value);

        $itemTable->clear();
        $itemTable->string_value = $itemValue;
        self::assertTrue($itemTable->find() > 0);
        assertEquals(1, $itemTable->size());
        $itemTable->next();
        assertEquals($itemValue, $itemTable->string_value);
        $itemTable->int_value = 77;
        $tableKeyValue = $itemTable->id;
        $itemTable->save();
        assertEquals($itemValue, $itemTable->string_value);
        assertEquals($tableKeyValue, $itemTable->id);

        $itemTable->clear();
        $itemTable->id = $tableKeyValue;
        $itemTable->string_value = $itemValue;
        self::assertTrue($itemTable->find() > 0);
        $itemTable->next();
        assertEquals(1, $itemTable->size());
        assertEquals($itemValue, $itemTable->string_value);
        assertEquals(77, $itemTable->int_value);
        assertEquals($tableKeyValue, $itemTable->id);
    }

    public function testDeleteItems() {
        $itemTable = TestHappyPath::$itemTable;
        $itemTable->clear();
        $itemTable->gt('id', 1);
        self::assertTrue($itemTable->find() > 0);
        while ($itemTable->next()) {
            $itemTable->delete();
        }

        $itemTable->clear();
        $itemTable->gt('id', 1);
        self::assertTrue($itemTable->find() == 0);
        assertEquals(0, $itemTable->size());
    }

}