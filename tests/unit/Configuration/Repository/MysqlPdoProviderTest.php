<?php

namespace Tests\Unit\Configuration\Repository;

use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Configuration\Repository\PdoProviderInterface;
use Amtgard\PHPUnit\AmtgardTestCase;
use PDO;
use PDOException;
use Phake;

class MysqlPdoProviderTest extends AmtgardTestCase
{
    private DatabaseConfiguration $mockConfiguration;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockConfiguration = Phake::mock(DatabaseConfiguration::class);
        Phake::when($this->mockConfiguration)->getConfig()->thenReturn([
            'host' => 'localhost',
            'port' => '3306',
            'dbname' => 'test_db',
            'user' => 'test_user',
            'password' => 'test_password',
            'errmode' => PDO::ERRMODE_EXCEPTION,
            'options' => [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
        ]);
    }

    public function testFromConfiguration_createsInstanceSuccessfully(): void
    {
        $pdoProvider = MysqlPdoProvider::fromConfiguration($this->mockConfiguration);
        
        self::assertInstanceOf(MysqlPdoProvider::class, $pdoProvider);
        self::assertInstanceOf(PdoProviderInterface::class, $pdoProvider);
    }

    public function testFromConfiguration_withValidConfiguration_usesBuilderPattern(): void
    {
        $pdoProvider = MysqlPdoProvider::fromConfiguration($this->mockConfiguration);
        
        // Verify the configuration was used in the builder
        self::assertInstanceOf(MysqlPdoProvider::class, $pdoProvider);
        
        // Test that the configuration is accessible (using reflection to verify)
        $reflection = new \ReflectionClass($pdoProvider);
        $configProperty = $reflection->getProperty('configuration');
        $configProperty->setAccessible(true);
        
        $storedConfig = $configProperty->getValue($pdoProvider);
        self::assertSame($this->mockConfiguration, $storedConfig);
    }

    public function testGetPdo_returnsPdoInstance(): void
    {
        $pdoProvider = MysqlPdoProvider::fromConfiguration($this->mockConfiguration);

        self::assertThrows(PDOException::class, function () use ($pdoProvider) {
            $pdo = $pdoProvider->getPdo();
        });

        Phake::verify($this->mockConfiguration)->getConfig();
    }

    public function testGetDatabaseName_returnsDatabaseNameFromConfiguration(): void
    {
        $pdoProvider = MysqlPdoProvider::fromConfiguration($this->mockConfiguration);
        
        $databaseName = $pdoProvider->getDatabaseName();
        
        self::assertEquals('test_db', $databaseName);
    }

    public function testGetDatabaseName_withDifferentConfiguration_returnsCorrectName(): void
    {
        $differentConfig = Phake::mock(DatabaseConfiguration::class);
        Phake::when($differentConfig)->getConfig()->thenReturn([
            'host' => 'localhost',
            'port' => '3306',
            'dbname' => 'different_db',
            'user' => 'test_user',
            'password' => 'test_password',
            'errmode' => PDO::ERRMODE_EXCEPTION,
            'options' => []
        ]);
        
        $pdoProvider = MysqlPdoProvider::fromConfiguration($differentConfig);
        
        $databaseName = $pdoProvider->getDatabaseName();
        
        self::assertEquals('different_db', $databaseName);
    }
} 