<?php

namespace Tests\util;

class Constants
{
    public static string $PDO_RECORD_SET_JSON = <<<PDO_RECORD_SET_JSON
{
    "records": [
        {
            "id": 1,
            "0": 1,
            "int_value": 3,
            "1": 3,
            "string_value": "2",
            "2": "2",
            "datetime_value": null,
            "3": null,
            "blob_value": null,
            "4": null,
            "text_value": null,
            "5": null,
            "json_value": null,
            "6": null,
            "binary_value": null,
            "7": null,
            "boolean_value": null,
            "8": null,
            "enum_value": null,
            "9": null,
            "double_value": null,
            "10": null,
            "uuid_value": null,
            "11": null,
            "decimal_value": null,
            "12": null
        }
    ],
    "pdoDefinition": [
        {
            "native_type": "LONG",
            "pdo_type": 1,
            "flags": [
                "not_null",
                "primary_key"
            ],
            "table": "integ",
            "name": "id",
            "len": 11,
            "precision": 0
        },
        {
            "native_type": "LONG",
            "pdo_type": 1,
            "flags": [],
            "table": "integ",
            "name": "int_value",
            "len": 11,
            "precision": 0
        },
        {
            "native_type": "VAR_STRING",
            "pdo_type": 2,
            "flags": [],
            "table": "integ",
            "name": "string_value",
            "len": 765,
            "precision": 0
        },
        {
            "native_type": "DATETIME",
            "pdo_type": 2,
            "flags": [],
            "table": "integ",
            "name": "datetime_value",
            "len": 19,
            "precision": 0
        },
        {
            "native_type": "BLOB",
            "pdo_type": 2,
            "flags": [
                "blob"
            ],
            "table": "integ",
            "name": "blob_value",
            "len": 65535,
            "precision": 0
        },
        {
            "native_type": "BLOB",
            "pdo_type": 2,
            "flags": [
                "blob"
            ],
            "table": "integ",
            "name": "text_value",
            "len": 196605,
            "precision": 0
        },
        {
            "native_type": "BLOB",
            "pdo_type": 2,
            "flags": [
                "blob"
            ],
            "table": "integ",
            "name": "json_value",
            "len": 4294967295,
            "precision": 0
        },
        {
            "native_type": "STRING",
            "pdo_type": 2,
            "flags": [],
            "table": "integ",
            "name": "binary_value",
            "len": 1,
            "precision": 0
        },
        {
            "native_type": "TINY",
            "pdo_type": 1,
            "flags": [],
            "table": "integ",
            "name": "boolean_value",
            "len": 1,
            "precision": 0
        },
        {
            "native_type": "STRING",
            "pdo_type": 2,
            "flags": [],
            "table": "integ",
            "name": "enum_value",
            "len": 15,
            "precision": 0
        },
        {
            "native_type": "DOUBLE",
            "pdo_type": 2,
            "flags": [],
            "table": "integ",
            "name": "double_value",
            "len": 22,
            "precision": 31
        },
        {
            "native_type": "STRING",
            "pdo_type": 2,
            "flags": [],
            "table": "integ",
            "name": "uuid_value",
            "len": 108,
            "precision": 0
        },
        {
            "native_type": "NEWDECIMAL",
            "pdo_type": 2,
            "flags": [],
            "table": "integ",
            "name": "decimal_value",
            "len": 11,
            "precision": 0
        }
    ],
    "fieldDefinition": [
        {
            "name": "id",
            "value": "id",
            "type": 1,
            "nativeType": "LONG",
            "nullable": false,
            "extra": null
        },
        {
            "name": "int_value",
            "value": "int_value",
            "type": 1,
            "nativeType": "LONG",
            "nullable": true,
            "extra": null
        },
        {
            "name": "string_value",
            "value": "string_value",
            "type": 2,
            "nativeType": "VAR_STRING",
            "nullable": true,
            "extra": null
        },
        {
            "name": "datetime_value",
            "value": "datetime_value",
            "type": 2,
            "nativeType": "DATETIME",
            "nullable": true,
            "extra": null
        },
        {
            "name": "blob_value",
            "value": "blob_value",
            "type": 2,
            "nativeType": "BLOB",
            "nullable": true,
            "extra": null
        },
        {
            "name": "text_value",
            "value": "text_value",
            "type": 2,
            "nativeType": "BLOB",
            "nullable": true,
            "extra": null
        },
        {
            "name": "json_value",
            "value": "json_value",
            "type": 2,
            "nativeType": "BLOB",
            "nullable": true,
            "extra": null
        },
        {
            "name": "binary_value",
            "value": "binary_value",
            "type": 2,
            "nativeType": "STRING",
            "nullable": true,
            "extra": null
        },
        {
            "name": "boolean_value",
            "value": "boolean_value",
            "type": 1,
            "nativeType": "TINY",
            "nullable": true,
            "extra": null
        },
        {
            "name": "enum_value",
            "value": "enum_value",
            "type": 2,
            "nativeType": "STRING",
            "nullable": true,
            "extra": null
        },
        {
            "name": "double_value",
            "value": "double_value",
            "type": 2,
            "nativeType": "DOUBLE",
            "nullable": true,
            "extra": null
        },
        {
            "name": "uuid_value",
            "value": "uuid_value",
            "type": 2,
            "nativeType": "STRING",
            "nullable": true,
            "extra": null
        },
        {
            "name": "decimal_value",
            "value": "decimal_value",
            "type": 2,
            "nativeType": "NEWDECIMAL",
            "nullable": true,
            "extra": null
        }
    ]
}
PDO_RECORD_SET_JSON;

    public static string $DESCRIBE_TABLE_INTEG = <<<DESCRIBE_TABLE
[
    {
        "Field": "id",
        "Type": "int(11) unsigned",
        "Null": "NO",
        "Key": "PRI",
        "Default": null,
        "Extra": "auto_increment"
    },
    {
        "Field": "int_value",
        "Type": "int(11)",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "string_value",
        "Type": "varchar(255)",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "datetime_value",
        "Type": "datetime",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "blob_value",
        "Type": "blob",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "text_value",
        "Type": "text",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "json_value",
        "Type": "longtext",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "binary_value",
        "Type": "binary(1)",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "boolean_value",
        "Type": "tinyint(1)",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "enum_value",
        "Type": "enum('alpha','beta')",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "double_value",
        "Type": "double",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "uuid_value",
        "Type": "uuid",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    },
    {
        "Field": "decimal_value",
        "Type": "decimal(10,0)",
        "Null": "YES",
        "Key": "",
        "Default": null,
        "Extra": ""
    }
]
DESCRIBE_TABLE;


    // From MysqlDatabaseTest::testCaptureDescribeTable()
    public static string $SELECT_INTEG_DEFINITION = <<<SELECT
[
    {
        "native_type": "LONG",
        "pdo_type": 1,
        "flags": [
            "not_null",
            "primary_key"
        ],
        "table": "integ",
        "name": "id",
        "len": 11,
        "precision": 0
    },
    {
        "native_type": "LONG",
        "pdo_type": 1,
        "flags": [],
        "table": "integ",
        "name": "int_value",
        "len": 11,
        "precision": 0
    },
    {
        "native_type": "VAR_STRING",
        "pdo_type": 2,
        "flags": [],
        "table": "integ",
        "name": "string_value",
        "len": 765,
        "precision": 0
    },
    {
        "native_type": "DATETIME",
        "pdo_type": 2,
        "flags": [],
        "table": "integ",
        "name": "datetime_value",
        "len": 19,
        "precision": 0
    },
    {
        "native_type": "BLOB",
        "pdo_type": 2,
        "flags": [
            "blob"
        ],
        "table": "integ",
        "name": "blob_value",
        "len": 65535,
        "precision": 0
    },
    {
        "native_type": "BLOB",
        "pdo_type": 2,
        "flags": [
            "blob"
        ],
        "table": "integ",
        "name": "text_value",
        "len": 196605,
        "precision": 0
    },
    {
        "native_type": "BLOB",
        "pdo_type": 2,
        "flags": [
            "blob"
        ],
        "table": "integ",
        "name": "json_value",
        "len": 4294967295,
        "precision": 0
    },
    {
        "native_type": "STRING",
        "pdo_type": 2,
        "flags": [],
        "table": "integ",
        "name": "binary_value",
        "len": 1,
        "precision": 0
    },
    {
        "native_type": "TINY",
        "pdo_type": 1,
        "flags": [],
        "table": "integ",
        "name": "boolean_value",
        "len": 1,
        "precision": 0
    },
    {
        "native_type": "STRING",
        "pdo_type": 2,
        "flags": [],
        "table": "integ",
        "name": "enum_value",
        "len": 15,
        "precision": 0
    },
    {
        "native_type": "DOUBLE",
        "pdo_type": 2,
        "flags": [],
        "table": "integ",
        "name": "double_value",
        "len": 22,
        "precision": 31
    },
    {
        "native_type": "STRING",
        "pdo_type": 2,
        "flags": [],
        "table": "integ",
        "name": "uuid_value",
        "len": 108,
        "precision": 0
    },
    {
        "native_type": "NEWDECIMAL",
        "pdo_type": 2,
        "flags": [],
        "table": "integ",
        "name": "decimal_value",
        "len": 11,
        "precision": 0
    }
]
SELECT;

    public static string $SELECT_INTEG_FETCH_DATA = <<<FETCH_DATA
{
    "id": 1,
    "0": 1,
    "int_value": 3,
    "1": 3,
    "string_value": "2",
    "2": "2",
    "datetime_value": null,
    "3": null,
    "blob_value": null,
    "4": null,
    "text_value": null,
    "5": null,
    "json_value": null,
    "6": null,
    "binary_value": null,
    "7": null,
    "boolean_value": null,
    "8": null,
    "enum_value": null,
    "9": null,
    "double_value": null,
    "10": null,
    "uuid_value": null,
    "11": null,
    "decimal_value": null,
    "12": null
}
FETCH_DATA;

}