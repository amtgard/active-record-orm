<?php


namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\OrmConfiguration\FileBasedActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Exception\ValueNotSetException;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\TableFactory;
use Amtgard\PHPUnit\AmtgardTestCase;
use DateTime;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertGreaterThan;
use function PHPUnit\Framework\assertNotEqualsIgnoringCase;
use function PHPUnit\Framework\assertTrue;

class TableTest extends AmtgardTestCase
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
        $provider = MysqlPdoProvider::fromConfiguration($config);
        TableTest::$db = Database::fromProvider($provider);

        TableTest::$tablePolicy = UncachedDataAccessPolicy::builder()->database(TableTest::$db)->build();;

        TableTest::$itemTable = TableFactory::build(TableTest::$db, TableTest::$tablePolicy, 'integ');

        self::resetTable();

    }

    private static function resetTable()
    {
        TableTest::$db->clear();
        TableTest::$db->execute("truncate table integ");

        TableTest::$db->clear();
        TableTest::$db->string_value = "2";
        TableTest::$db->int_value = 3;
        TableTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        TableTest::$db->clear();
        TableTest::$db->string_value = "4";
        TableTest::$db->int_value = 5;
        TableTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");

        TableTest::$db->clear();
        TableTest::$db->string_value = "Bunny Rabbit Foo-Foo";
        TableTest::$db->int_value = 6;
        TableTest::$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");
        TableTest::$db->clear();
    }

    public function testFindWithoutNext_ThrowsInformativeException()
    {
        $itemTable = TableTest::$itemTable;
        $itemTable->clear();
        $itemTable->id = 1;
        assertEquals(1, $itemTable->find());
        assertEquals(1, $itemTable->size());
        $this->assertThrows(ValueNotSetException::class, fn() => $v = $itemTable->string_value);
    }

}