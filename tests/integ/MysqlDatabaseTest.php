<?php

namespace Tests\Integration;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Configuration\Database\DatabaseConfiguration;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class MysqlDatabaseTest extends TestCase
{
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
    }

    public function testBasicQuery() {
        $config = DatabaseConfiguration::fromEnvironment();
        $db = Database::fromConfig($config);

        $db->clear();
        $db->execute("truncate table integtable");
        $db->clear();
        $db->string_value = "2";
        $db->int_value = 3;
        $db->execute("insert into integtable (string_value, int_value) values (:string_value, :int_value)");
        $db->clear();
        $records = $db->execute("select * from integtable");
        $records->next();

        self::assertEquals(1, $records->size());
        self::assertEquals(2, $records->string_value);
        self::assertEquals(3, $records->int_value);
        self::assertEquals(1, $records->id);
        $definition = json_encode($records->getDefinition());
        self::assertEquals(6, count($records->getDefinition()));
    }
}