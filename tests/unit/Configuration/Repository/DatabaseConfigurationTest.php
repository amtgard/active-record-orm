<?php

namespace Tests\Unit\Configuration\Repository;

use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\PHPUnit\AmtgardTestCase;
use PDO;

class DatabaseConfigurationTest extends AmtgardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment variables
        $_ENV['DB_HOST'] = 'test_host';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASS'] = 'test_password';
        $_ENV['DB_NAME'] = 'test_database';
    }

    public function testFromEnvironment_createsInstanceSuccessfully(): void
    {
        $config = DatabaseConfiguration::fromEnvironment();
        
        self::assertInstanceOf(DatabaseConfiguration::class, $config);
    }

    public function testGetConfig_returnsCorrectConfigurationArray(): void
    {
        $config = DatabaseConfiguration::fromEnvironment();
        $configArray = $config->getConfig();
        
        self::assertEquals('test_host', $configArray['host']);
        self::assertEquals('3306', $configArray['port']);
        self::assertEquals('test_user', $configArray['user']);
        self::assertEquals('test_password', $configArray['password']);
        self::assertEquals('test_database', $configArray['dbname']);
        self::assertEquals(PDO::ERRMODE_EXCEPTION, $configArray['errmode']);
        self::assertArrayHasKey('options', $configArray);
        self::assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $configArray['options']);
        self::assertEquals("SET NAMES 'utf8'", $configArray['options'][PDO::MYSQL_ATTR_INIT_COMMAND]);
    }
} 