<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\PHPUnit\AmtgardTestCase;
use PDO;
use Tests\util\Constants;

class FieldTypeTest extends AmtgardTestCase
{
    function testFieldToPdoType() {
        $pdoTypesMap = [
            PDO::PARAM_BOOL => FieldType::BOOL,
            PDO::PARAM_INT => FieldType::INTEGER,
            PDO::PARAM_STR => FieldType::STRING,
            PDO::PARAM_LOB => FieldType::LOB,
            PDO::PARAM_NULL => FieldType::NULL
        ];
        foreach ($pdoTypesMap as $pdoType => $fieldType) {
            self::assertEquals($pdoType, FieldType::toPdoType($fieldType));
        }
    }

    function testFromPdoType() {
        $pdoTypesMap = [
            PDO::PARAM_BOOL => [ FieldType::BOOL, 'BOOL' ],
            PDO::PARAM_INT => [ FieldType::INTEGER, 'INTEGER' ],
            PDO::PARAM_STR => [ FieldType::STRING, 'STRING' ],
            PDO::PARAM_LOB => [ FieldType::LOB, 'LOB' ],
            PDO::PARAM_NULL => [ FieldType::NULL, 'NULL' ],
            -1 => [ FieldType::DATETIME, 'DATETIME' ],
            -2 => [ FieldType::DOUBLE, 'DOUBLE' ]
        ];
        foreach ($pdoTypesMap as $pdoType => $fieldType) {
            self::assertEquals($fieldType[0], FieldType::fromPdoType($pdoType, $fieldType[1]));
        }
    }

    function testFromBasicTableTypes() {
        foreach ($this->buildDescribeTable() as $tableTypeRecord) {
            self::assertDoesNotThrow(function() use ($tableTypeRecord) {
                FieldType::fromTableType($tableTypeRecord['Type']);
            });
        }
    }

    function buildDescribeTable(): array {
        // From MysqlDatabaseTest::testCaptureDescribeTable()
        return json_decode(Constants::$DESCRIBE_TABLE_INTEG, true);
    }
}