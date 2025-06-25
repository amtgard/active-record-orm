<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Utility\Constants;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\assertEquals;

class TableSchemaTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private FieldDefinition $mockPrimaryKey;
    private FieldDefinition $mockField1;
    private FieldDefinition $mockField2;
    private TableSchema $tableSchema;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $this->mockField1 = Phake::mock(FieldDefinition::class);
        $this->mockField2 = Phake::mock(FieldDefinition::class);
        
        Phake::when($this->mockPrimaryKey)->getName()->thenReturn('id');
        Phake::when($this->mockField1)->getName()->thenReturn('name');
        Phake::when($this->mockField2)->getName()->thenReturn('email');
        
        $this->tableSchema = TableSchemaImpl::builder()
            ->tableName('test_table')
            ->database($this->mockDatabase)
            ->fields([
                'id' => $this->mockPrimaryKey,
                'name' => $this->mockField1,
                'email' => $this->mockField2
            ])
            ->primaryKey($this->mockPrimaryKey)
            ->build();
    }

    public function testHasField_withNoMatch() {
        self::assertThrows(\InvalidArgumentException::class, function () {
            self::assertFalse($this->tableSchema->hasField('mame'));
        });

        try {
            $this->tableSchema->hasField('mame');
        } catch (\InvalidArgumentException $e) {
            self::assertEquals(sprintf(Constants::$TABLESCHEMA_FIELD_MISS_ERROR, 'mame', 'test_table', 'name'), $e->getMessage());
        }
    }

    public function testGetTableName_returnsCorrectTableName(): void
    {
        assertEquals('test_table', $this->tableSchema->getTableName());
    }

    public function testGetTableName_returnsSetTableName(): void
    {
        $customSchema = TableSchemaImpl::builder()
            ->tableName('custom_table')
            ->database($this->mockDatabase)
            ->fields(['id' => $this->mockPrimaryKey])
            ->primaryKey($this->mockPrimaryKey)
            ->build();
            
        assertEquals('custom_table', $customSchema->getTableName());
    }

    public function testGetPrimaryKey_returnsCorrectPrimaryKey(): void
    {
        assertEquals($this->mockPrimaryKey, $this->tableSchema->getPrimaryKey());
    }

    public function testGetPrimaryKey_returnsSetPrimaryKey(): void
    {
        $customPrimaryKey = Phake::mock(FieldDefinition::class);
        Phake::when($customPrimaryKey)->getName()->thenReturn('custom_id');
        
        $customSchema = TableSchemaImpl::builder()
            ->tableName('test_table')
            ->database($this->mockDatabase)
            ->fields(['custom_id' => $customPrimaryKey])
            ->primaryKey($customPrimaryKey)
            ->build();
            
        assertEquals($customPrimaryKey, $customSchema->getPrimaryKey());
    }

    public function testPrimaryKeyIsSet_withPrimaryKeyInFieldSet_returnsTrue(): void
    {
        $fieldSet = Phake::mock(FieldSet::class);
        Phake::when($fieldSet)->getFieldNames()->thenReturn(['id', 'name', 'email']);
        
        self::assertTrue($this->tableSchema->primaryKeyIsSet($fieldSet));
    }

    public function testPrimaryKeyIsSet_withoutPrimaryKeyInFieldSet_returnsFalse(): void
    {
        $fieldSet = Phake::mock(FieldSet::class);
        Phake::when($fieldSet)->getFieldNames()->thenReturn(['name', 'email']);
        
        self::assertFalse($this->tableSchema->primaryKeyIsSet($fieldSet));
    }
}

class TableSchemaImpl extends TableSchema
{
    // Concrete implementation for testing
}
