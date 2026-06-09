<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use PDO;
use Phinx\Db\Adapter\UnsupportedColumnTypeException;

enum FieldType: int
{
    case NULL = 0;
    case BOOL = 5;
    case INTEGER = 1;
    case STRING = 2;
    case LOB = 3;
    case DATETIME = 100;
    case BINARY = 101;
    case ENUM = 102;
    case DOUBLE = 103;
    case UUID = 104;
    case DECIMAL = 105;

    private static function pdoToFieldMap(): array {
        return [
            PDO::PARAM_BOOL => FieldType::BOOL,
            PDO::PARAM_INT => FieldType::INTEGER,
            PDO::PARAM_STR => FieldType::STRING,
            PDO::PARAM_LOB => FieldType::LOB,
            PDO::PARAM_NULL => FieldType::NULL
        ];
    }

    private static function fieldTypeToPdoTypeMap() {
        return [
            FieldType::NULL->name => PDO::PARAM_NULL,
            FieldType::BOOL->name => PDO::PARAM_BOOL,
            FieldType::INTEGER->name => PDO::PARAM_INT,
            FieldType::STRING->name => PDO::PARAM_STR,
            FieldType::LOB->name => PDO::PARAM_LOB,
            FieldType::DATETIME->name => PDO::PARAM_STR,
            FieldType::BINARY->name => PDO::PARAM_STR,
            FieldType::ENUM->name => PDO::PARAM_STR,
            FieldType::DOUBLE->name => PDO::PARAM_STR,
            FieldType::UUID->name => PDO::PARAM_STR,
            FieldType::DECIMAL->name => PDO::PARAM_STR
        ];
    }

    public static function mariaDbNativeTypeMap(): array {
        return [
            'LONG' => FieldType::INTEGER,
            'VAR_STRING' => FieldType::STRING,
            'DATETIME' => FieldType::DATETIME,
            'BLOB' => FieldType::LOB, // ?? String, BLOB?
            'STRING' => FieldType::STRING,
            'TINY' => FieldType::INTEGER,
            'DOUBLE' => FieldType::DOUBLE,
            'NEWDECIMAL' => FieldType::DECIMAL
        ];
    }

    public static function toPdoType(FieldType $fieldType): int {
        return FieldType::fieldTypeToPdoTypeMap()[$fieldType->name];
    }

    public static function fromPdoType(int $pdo, string $nativeType): FieldType {
        $type = self::pdoToFieldMap()[$pdo] ?? null;
        if (is_null($type)) switch ($nativeType) {
            case 'DATETIME': return FieldType::DATETIME;
            case 'DOUBLE': return FieldType::DOUBLE;
        }
        return $type;
    }

    public static function fromTableType($fieldType): FieldType {
        $normalized = strtolower(trim((string) $fieldType));
        $normalized = preg_replace('/\s+unsigned$/', '', $normalized);
        $normalized = preg_replace('/\(.+?\)/', '', $normalized);
        $normalized = trim($normalized);
        switch ($normalized) {
            case 'varchar': return FieldType::STRING;
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'int': return FieldType::INTEGER;
            case 'json': return FieldType::STRING;
            case 'datetime': return FieldType::DATETIME;
            case 'longtext':
            case 'tinytext': return FieldType::STRING;
            case 'text': return FieldType::STRING;
            case 'blob': return FieldType::LOB;
            case 'binary': return FieldType::BINARY;
            case 'enum': return FieldType::ENUM;
            case 'decimal': return FieldType::DECIMAL;
            case 'float':
            case 'double': return FieldType::DOUBLE;
            case 'uuid': return FieldType::UUID;
        }

        throw new UnsupportedColumnTypeException('Column of type ' . $fieldType . ' is not currently supported.');
    }
}