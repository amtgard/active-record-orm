<?php

namespace Tests\bootstrap;

use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Dotenv\Dotenv;
use function PHPUnit\Framework\assertTrue;

class ComparisonTests extends \PHPUnit\Framework\TestCase
{
    public function testBuildUncachedTableSchemaJson() {
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
        $db = Database::fromProvider($provider);

        $schema = UncachedTableSchema::builder()
            ->tableName("integ")
            ->database($db)
            ->build();

        $json = json_encode($schema, JSON_PRETTY_PRINT);
        echo $json;
        assertTrue(true);
    }
}