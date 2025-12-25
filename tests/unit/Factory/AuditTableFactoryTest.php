<?php

namespace Tests\Unit\Factory;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Factory\AuditTableFactory;
use Amtgard\ActiveRecordOrm\Feature\AuditTable;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class AuditTableFactoryTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private DataAccessPolicy $mockPolicy;
    private TableSchema $mockSourceTableSchema;
    private TableSchema $mockAuditTableSchema;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockPolicy = Phake::mock(DataAccessPolicy::class);
        $this->mockSourceTableSchema = Phake::mock(TableSchema::class);
        $this->mockAuditTableSchema = Phake::mock(TableSchema::class);

        $this->setupPolicyMocks();
    }

    private function setupPolicyMocks(): void
    {
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_table')->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_table_audit_log')->thenReturn($this->mockAuditTableSchema);
    }

    public function testBuild_createsAuditTableInstance(): void
    {
        $table = AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, 'test_table');
        
        self::assertInstanceOf(TableInterface::class, $table);
        self::assertInstanceOf(Table::class, $table);
    }

    public function testBuild_createsAuditTableWithCorrectSuffix(): void
    {
        $tableName = 'users';
        $expectedAuditTableName = 'users_audit_log';
        
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($tableName)->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($expectedAuditTableName)->thenReturn($this->mockAuditTableSchema);
        
        $table = AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, $tableName);
        
        self::assertInstanceOf(TableInterface::class, $table);
        // Verify that the audit table schema was requested with the _audit_log suffix
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy($expectedAuditTableName);
    }

    public function testBuild_createsBothSourceAndAuditTables(): void
    {
        $tableName = 'test_table';
        
        $table = AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, $tableName);
        
        self::assertInstanceOf(TableInterface::class, $table);
        // Verify both source and audit table schemas were requested
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy($tableName);
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy($tableName . '_audit_log');
    }

    public function testBuild_createsAuditTableWithSourceTableConfigured(): void
    {
        $table = AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, 'test_table');
        
        self::assertInstanceOf(AuditTable::class, $table);
        self::assertInstanceOf(TableInterface::class, $table);
    }

    public function testAuditMapperSupplier_createsEntityMapper(): void
    {
        $mapperName = 'test_mapper';
        
        // Need to set up policy mocks for the mapper supplier
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($mapperName)->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($mapperName . '_audit_log')->thenReturn($this->mockAuditTableSchema);
        
        $mapper = AuditTableFactory::auditMapperSupplier($this->mockDatabase, $this->mockPolicy, $mapperName);
        
        self::assertInstanceOf(EntityMapper::class, $mapper);
    }

    public function testAuditMapperSupplier_usesAuditTableFactoryBuild(): void
    {
        $mapperName = 'test_mapper';
        
        // Set up policy mocks
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($mapperName)->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($mapperName . '_audit_log')->thenReturn($this->mockAuditTableSchema);
        
        $mapper = AuditTableFactory::auditMapperSupplier($this->mockDatabase, $this->mockPolicy, $mapperName);
        
        self::assertInstanceOf(EntityMapper::class, $mapper);
        // Verify that build was called (indirectly via applyTableSchemaPolicy calls)
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy($mapperName);
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy($mapperName . '_audit_log');
    }

    public function testAuditMapperSupplier_setsMapperNameCorrectly(): void
    {
        $mapperName = 'custom_mapper_name';
        
        // Set up policy mocks
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($mapperName)->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($mapperName . '_audit_log')->thenReturn($this->mockAuditTableSchema);
        
        $mapper = AuditTableFactory::auditMapperSupplier($this->mockDatabase, $this->mockPolicy, $mapperName);
        
        self::assertInstanceOf(EntityMapper::class, $mapper);
        // The mapper should have the correct name set
        self::assertEquals($mapperName, $mapper->getName());
    }

    public function testBuild_callsPolicyForAllRequiredSchemas(): void
    {
        $tableName = 'products';
        $auditTableName = $tableName . '_audit_log';
        
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($tableName)->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy($auditTableName)->thenReturn($this->mockAuditTableSchema);
        
        $table = AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, $tableName);
        
        self::assertInstanceOf(TableInterface::class, $table);
        // Verify policy was called for source table (multiple times due to internal table and query builder creation)
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy($tableName);
        // Verify policy was called for audit table (multiple times)
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy($auditTableName);
    }
}

