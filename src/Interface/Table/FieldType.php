<?php

namespace Amtgard\ActiveRecordOrm\Interface\Table;

use PDO;

enum FieldType
{
    case NULL;
    case BOOL;
    case INTEGER;
    case STRING;
    case LOB;
    case DATETIME;


    private static function fieldToPdoMap(): array {
        return array_combine(array_map(function($v): string { return $v->name; }, self::pdoToFieldMap()), array_keys(self::pdoToFieldMap())) + [ FieldType::DATETIME->name => PDO::PARAM_STR ];
    }

    private static function pdoToFieldMap(): array {
        return [
            PDO::PARAM_BOOL => FieldType::BOOL,
            PDO::PARAM_INT => FieldType::INTEGER,
            PDO::PARAM_STR => FieldType::STRING,
            PDO::PARAM_LOB => FieldType::LOB,
            PDO::PARAM_NULL => FieldType::NULL
        ];
    }

    public static function toPdoType(FieldType $fieldType): int {
        return self::fieldToPdoMap()[$fieldType->name];
    }

    public static function fromPdoType(int $pdo, string $nativeType): FieldType {
        $type = self::pdoToFieldMap()[$pdo];
        if (is_null($type)) switch ($nativeType) {
            case 'DATETIME': return FieldType::DATETIME;
        }
        return $type;
    }
}