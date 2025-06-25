<?php

namespace Tests\Unit\Repository;

use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Configuration\Repository\PdoProviderInterface;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\PHPUnit\AmtgardTestCase;
use PDO;
use PDOStatement;
use Phake;

class DatabaseTest extends AmtgardTestCase
{
    private PdoProviderInterface $mockPdoProvider;
    private DatabaseConfiguration $mockConfig;
    private PDO $mockPdo;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockConfig = Phake::mock(DatabaseConfiguration::class);
        Phake::when($this->mockConfig)->getConfig()->thenReturn([
            DatabaseConfiguration::$ENV_HOST_KEY => 'localhost',
            DatabaseConfiguration::$ENV_PORT_KEY => '3306',
            DatabaseConfiguration::$ENV_NAME_KEY => 'test_db',
            DatabaseConfiguration::$ENV_USER_KEY => 'test_user',
            DatabaseConfiguration::$ENV_PASSWORD_KEY => 'test_pass',
            DatabaseConfiguration::$ENV_ERRMODE_KEY => PDO::ERRMODE_EXCEPTION,
            DatabaseConfiguration::$ENV_OPTIONS_KEY => [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
        ]);
        
        $this->mockPdo = Phake::mock(PDO::class);
        $this->mockPdoProvider = Phake::mock(PdoProviderInterface::class);

        Phake::when($this->mockPdoProvider)->getPdo()->thenReturn($this->mockPdo);
        Phake::when($this->mockPdoProvider)->getDatabaseName()->thenReturn('test_db');
    }

    public function testFromConfig_createsInstanceSuccessfully(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        self::assertInstanceOf(Database::class, $database);
    }

    public function testFromConfig_withValidConfiguration_initializesDatabase(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        // Test that the object is created and configured
        self::assertInstanceOf(Database::class, $database);
        
        // Verify the configuration was used
        Phake::verify($this->mockPdoProvider)->getPdo();
    }

    public function testExecute_withValidQuery_returnsRecordSet(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        $sql = "SELECT * FROM test_table";
        $mockStatement = Phake::mock(PDOStatement::class);
        
        Phake::when($this->mockPdo)->prepare($sql)->thenReturn($mockStatement);
        Phake::when($mockStatement)->execute()->thenReturn(true);
        
        // Use reflection to set the private PDO instance
        $reflection = new \ReflectionClass($database);
        $pdoProperty = $reflection->getProperty('__dbh');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($database, $this->mockPdo);
        
        $result = $database->execute($sql);
        
        self::assertInstanceOf(RecordSet\PdoRecordSet::class, $result);
        Phake::verify($this->mockPdo)->prepare($sql);
        Phake::verify($mockStatement)->execute();
    }

    public function testExecute_withParameters_usesPreparedStatement(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        $sql = "SELECT * FROM test_table WHERE id = :id";
        $mockStatement = Phake::mock(PDOStatement::class);
        
        // Set a field value using the __set method
        $database->__set('id', 123);
        
        Phake::when($this->mockPdo)->prepare($sql)->thenReturn($mockStatement);
        Phake::when($mockStatement)->bindValue(':id', 123)->thenReturn(true);
        Phake::when($mockStatement)->execute()->thenReturn(true);
        
        // Use reflection to set the private PDO instance
        $reflection = new \ReflectionClass($database);
        $pdoProperty = $reflection->getProperty('__dbh');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($database, $this->mockPdo);
        
        $result = $database->execute($sql);
        
        self::assertInstanceOf(RecordSet\PdoRecordSet::class, $result);
        Phake::verify($mockStatement)->bindValue(':id', 123);
    }

    public function testExecuteQuery_withValidQuery_returnsRecordSet(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        $mockQuery = Phake::mock(Query::class);
        $params = ['name' => 'test', 'id' => 1];
        $sql = "SELECT * FROM test_table WHERE name = :name AND id = :id";
        
        Phake::when($mockQuery)->getSql()->thenReturn($sql);
        Phake::when($mockQuery)->getParams()->thenReturn($params);
        
        $mockStatement = Phake::mock(PDOStatement::class);
        
        Phake::when($this->mockPdo)->prepare($sql)->thenReturn($mockStatement);
        Phake::when($mockStatement)->bindValue(':name', 'test')->thenReturn(true);
        Phake::when($mockStatement)->bindValue(':id', 1)->thenReturn(true);
        Phake::when($mockStatement)->execute()->thenReturn(true);
        
        // Use reflection to set the private PDO instance
        $reflection = new \ReflectionClass($database);
        $pdoProperty = $reflection->getProperty('__dbh');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($database, $this->mockPdo);
        
        $result = $database->executeQuery($mockQuery);
        
        self::assertInstanceOf(RecordSet\PdoRecordSet::class, $result);
        Phake::verify($mockQuery)->getSql();
        Phake::verify($mockQuery)->getParams();
    }

    public function testExecuteQuery_withEmptyParams_returnsRecordSet(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        $mockQuery = Phake::mock(Query::class);
        $sql = "SELECT * FROM test_table";
        
        Phake::when($mockQuery)->getSql()->thenReturn($sql);
        Phake::when($mockQuery)->getParams()->thenReturn([]);
        
        $mockStatement = Phake::mock(PDOStatement::class);
        
        Phake::when($this->mockPdo)->prepare($sql)->thenReturn($mockStatement);
        Phake::when($mockStatement)->execute()->thenReturn(true);
        
        // Use reflection to set the private PDO instance
        $reflection = new \ReflectionClass($database);
        $pdoProperty = $reflection->getProperty('__dbh');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($database, $this->mockPdo);
        
        $result = $database->executeQuery($mockQuery);
        
        self::assertInstanceOf(RecordSet\PdoRecordSet::class, $result);
    }

    public function testFqTableName_returnsFullyQualifiedTableName(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);

        $tableName = 'users';
        $result = $database->fqTableName($tableName);
        
        self::assertEquals('test_db.users', $result);
    }

    public function testFqTableName_withDifferentTableName_returnsCorrectFqName(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        $tableName = 'products';
        $result = $database->fqTableName($tableName);
        
        self::assertEquals('test_db.products', $result);
    }

    public function testClear_clearsFieldsArray(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        // Set some fields using __set
        $database->__set('field1', 'value1');
        $database->__set('field2', 'value2');
        
        // Use reflection to verify fields are set
        $reflection = new \ReflectionClass($database);
        $fieldsProperty = $reflection->getProperty('__fields');
        $fieldsProperty->setAccessible(true);
        
        $fields = $fieldsProperty->getValue($database);
        self::assertEquals(['field1' => 'value1', 'field2' => 'value2'], $fields);
        
        $database->clear();
        
        // Verify fields are cleared
        $fields = $fieldsProperty->getValue($database);
        self::assertEquals([], $fields);
    }

    public function testClear_onEmptyFields_doesNothing(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        // Use reflection to check fields
        $reflection = new \ReflectionClass($database);
        $fieldsProperty = $reflection->getProperty('__fields');
        $fieldsProperty->setAccessible(true);
        
        // Verify fields are empty initially
        self::assertEquals([], $fieldsProperty->getValue($database));
        
        $database->clear();
        
        // Verify fields remain empty
        self::assertEquals([], $fieldsProperty->getValue($database));
    }

    public function testGetLastInsertId_returnsLastInsertId(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        $expectedId = '12345';
        Phake::when($this->mockPdo)->lastInsertId()->thenReturn($expectedId);
        
        // Use reflection to set the private PDO instance
        $reflection = new \ReflectionClass($database);
        $pdoProperty = $reflection->getProperty('__dbh');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($database, $this->mockPdo);
        
        $result = $database->getLastInsertId();
        
        self::assertEquals($expectedId, $result);
        Phake::verify($this->mockPdo)->lastInsertId();
    }

    public function testGetLastInsertId_withNoInsert_returnsEmptyString(): void
    {
        $database = Database::fromProvider($this->mockPdoProvider);
        
        Phake::when($this->mockPdo)->lastInsertId()->thenReturn('');
        
        // Use reflection to set the private PDO instance
        $reflection = new \ReflectionClass($database);
        $pdoProperty = $reflection->getProperty('__dbh');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($database, $this->mockPdo);
        
        $result = $database->getLastInsertId();
        
        self::assertEquals('', $result);
    }
} 