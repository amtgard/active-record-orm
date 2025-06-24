<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\PHPUnit\AmtgardTestCase;
use PDO;
use Phinx\Db\Adapter\UnsupportedColumnTypeException;

class FieldTypeTest extends AmtgardTestCase
{
    public function testToPdoType_withBasicTypes_returnsCorrectPdoTypes(): void
    {
        self::assertEquals(PDO::PARAM_BOOL, FieldType::toPdoType(FieldType::BOOL));
        self::assertEquals(PDO::PARAM_INT, FieldType::toPdoType(FieldType::INTEGER));
        self::assertEquals(PDO::PARAM_STR, FieldType::toPdoType(FieldType::STRING));
        self::assertEquals(PDO::PARAM_LOB, FieldType::toPdoType(FieldType::LOB));
        self::assertEquals(PDO::PARAM_NULL, FieldType::toPdoType(FieldType::NULL));
        self::assertEquals(PDO::PARAM_STR, FieldType::toPdoType(FieldType::DATETIME));
        self::assertEquals(PDO::PARAM_STR, FieldType::toPdoType(FieldType::BINARY));
        self::assertEquals(PDO::PARAM_STR, FieldType::toPdoType(FieldType::ENUM));
        self::assertEquals(PDO::PARAM_STR, FieldType::toPdoType(FieldType::DOUBLE));
        self::assertEquals(PDO::PARAM_STR, FieldType::toPdoType(FieldType::UUID));
        self::assertEquals(PDO::PARAM_STR, FieldType::toPdoType(FieldType::DECIMAL));
    }

    public function testFromPdoType_withBasicPdoTypes_returnsCorrectFieldTypes(): void
    {
        self::assertEquals(FieldType::BOOL, FieldType::fromPdoType(PDO::PARAM_BOOL, 'BOOL'));
        self::assertEquals(FieldType::INTEGER, FieldType::fromPdoType(PDO::PARAM_INT, 'INTEGER'));
        self::assertEquals(FieldType::STRING, FieldType::fromPdoType(PDO::PARAM_STR, 'STRING'));
        self::assertEquals(FieldType::LOB, FieldType::fromPdoType(PDO::PARAM_LOB, 'LOB'));
        self::assertEquals(FieldType::NULL, FieldType::fromPdoType(PDO::PARAM_NULL, 'NULL'));
        self::assertEquals(FieldType::DATETIME, FieldType::fromPdoType(-1, 'DATETIME'));
        self::assertEquals(FieldType::DOUBLE, FieldType::fromPdoType(-2, 'DOUBLE'));
    }

    public function testFromTableType_withBasicTypes_returnsCorrectFieldTypes(): void
    {
        self::assertEquals(FieldType::STRING, FieldType::fromTableType('varchar(255)'));
        self::assertEquals(FieldType::INTEGER, FieldType::fromTableType('int(11)'));
        self::assertEquals(FieldType::INTEGER, FieldType::fromTableType('tinyint(1)'));
        self::assertEquals(FieldType::DATETIME, FieldType::fromTableType('datetime'));
        self::assertEquals(FieldType::STRING, FieldType::fromTableType('text'));
        self::assertEquals(FieldType::STRING, FieldType::fromTableType('longtext'));
        self::assertEquals(FieldType::LOB, FieldType::fromTableType('blob'));
        self::assertEquals(FieldType::BINARY, FieldType::fromTableType('binary(16)'));
        self::assertEquals(FieldType::ENUM, FieldType::fromTableType('enum(\'a\',\'b\')'));
        self::assertEquals(FieldType::DECIMAL, FieldType::fromTableType('decimal(10,2)'));
        self::assertEquals(FieldType::DOUBLE, FieldType::fromTableType('float'));
        self::assertEquals(FieldType::DOUBLE, FieldType::fromTableType('double'));
        self::assertEquals(FieldType::UUID, FieldType::fromTableType('uuid'));
    }

    public function testFromTableType_withUnsupportedType_throwsException(): void
    {
        $this->expectException(UnsupportedColumnTypeException::class);
        $this->expectExceptionMessage('Column of type unsupported_type is not currently supported.');
        
        FieldType::fromTableType('unsupported_type');
    }

    public function testMariaDbNativeTypeMap_returnsCorrectTypeMappings(): void
    {
        $typeMap = FieldType::mariaDbNativeTypeMap();
        
        // Verify all expected mappings exist
        self::assertEquals(FieldType::INTEGER, $typeMap['LONG']);
        self::assertEquals(FieldType::STRING, $typeMap['VAR_STRING']);
        self::assertEquals(FieldType::DATETIME, $typeMap['DATETIME']);
        self::assertEquals(FieldType::LOB, $typeMap['BLOB']);
        self::assertEquals(FieldType::STRING, $typeMap['STRING']);
        self::assertEquals(FieldType::INTEGER, $typeMap['TINY']);
        self::assertEquals(FieldType::DOUBLE, $typeMap['DOUBLE']);
        self::assertEquals(FieldType::DECIMAL, $typeMap['NEWDECIMAL']);
        
        // Verify the map contains exactly the expected number of entries
        self::assertCount(8, $typeMap);
        
        // Verify all values are FieldType enum instances
        foreach ($typeMap as $nativeType => $fieldType) {
            self::assertInstanceOf(FieldType::class, $fieldType);
        }
    }
}