<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use PDO;

class FieldDefinitionTest extends AmtgardTestCase
{
    public function testFromColumnMetadata_withBasicPdoDefinition_createsFieldDefinition(): void
    {
        $pdoDefinition = [
            'name' => 'test_field',
            'pdo_type' => PDO::PARAM_INT,
            'native_type' => 'LONG',
            'flags' => ['not_null']
        ];
        $value = 123;

        $fieldDefinition = FieldDefinition::fromColumnMetadata($pdoDefinition, $value);

        self::assertEquals('test_field', $fieldDefinition->getName());
        self::assertEquals(FieldType::INTEGER, $fieldDefinition->getType());
        self::assertEquals($value, $fieldDefinition->getValue());
        self::assertEquals('LONG', $fieldDefinition->getNativeType());
        self::assertFalse($fieldDefinition->getNullable());
        self::assertNull($fieldDefinition->getExtra());
    }

    public function testFromColumnMetadata_withNullableField_createsNullableFieldDefinition(): void
    {
        $pdoDefinition = [
            'name' => 'nullable_field',
            'pdo_type' => PDO::PARAM_STR,
            'native_type' => 'VAR_STRING',
            'flags' => []
        ];
        $value = 'test_value';

        $fieldDefinition = FieldDefinition::fromColumnMetadata($pdoDefinition, $value);

        self::assertEquals('nullable_field', $fieldDefinition->getName());
        self::assertEquals(FieldType::STRING, $fieldDefinition->getType());
        self::assertEquals($value, $fieldDefinition->getValue());
        self::assertEquals('VAR_STRING', $fieldDefinition->getNativeType());
        self::assertTrue($fieldDefinition->getNullable());
        self::assertNull($fieldDefinition->getExtra());
    }

    public function testFromDescribeTable_withValidRecordSet_createsFieldDefinition(): void
    {
        $mockRecordSet = Phake::mock(RecordSet::class);
        Phake::when($mockRecordSet)->__get('Field')->thenReturn('test_field');
        Phake::when($mockRecordSet)->__get('Type')->thenReturn('varchar(255)');
        Phake::when($mockRecordSet)->__get('Extra')->thenReturn('auto_increment');
        Phake::when($mockRecordSet)->__get('Null')->thenReturn('NO');

        $fieldDefinition = FieldDefinition::fromDescribeTable($mockRecordSet);

        self::assertEquals('test_field', $fieldDefinition->getName());
        self::assertEquals(FieldType::STRING, $fieldDefinition->getType());
        self::assertEquals('varchar(255)', $fieldDefinition->getNativeType());
        self::assertEquals('auto_increment', $fieldDefinition->getExtra());
        self::assertFalse($fieldDefinition->getNullable());
    }

    public function testFromDescribeTable_withNullableField_createsNullableFieldDefinition(): void
    {
        $mockRecordSet = Phake::mock(RecordSet::class);
        Phake::when($mockRecordSet)->__get('Field')->thenReturn('nullable_field');
        Phake::when($mockRecordSet)->__get('Type')->thenReturn('int(11)');
        Phake::when($mockRecordSet)->__get('Extra')->thenReturn('');
        Phake::when($mockRecordSet)->__get('Null')->thenReturn('YES');

        $fieldDefinition = FieldDefinition::fromDescribeTable($mockRecordSet);

        self::assertEquals('nullable_field', $fieldDefinition->getName());
        self::assertEquals(FieldType::INTEGER, $fieldDefinition->getType());
        self::assertEquals('int(11)', $fieldDefinition->getNativeType());
        self::assertEquals('', $fieldDefinition->getExtra());
        self::assertTrue($fieldDefinition->getNullable());
    }

    public function testFromJson_withCompleteJsonData_createsFieldDefinition(): void
    {
        $jsonData = [
            'name' => 'json_field',
            'type' => FieldType::STRING->value,
            'value' => 'test_value',
            'nativeType' => 'varchar(255)',
            'nullable' => true,
            'extra' => 'some_extra'
        ];

        $fieldDefinition = FieldDefinition::fromJson($jsonData);

        self::assertEquals('json_field', $fieldDefinition->getName());
        self::assertEquals(FieldType::STRING, $fieldDefinition->getType());
        self::assertEquals('test_value', $fieldDefinition->getValue());
        self::assertEquals('varchar(255)', $fieldDefinition->getNativeType());
        self::assertTrue($fieldDefinition->getNullable());
        self::assertEquals('some_extra', $fieldDefinition->getExtra());
    }

    public function testFromJson_withMinimalJsonData_createsFieldDefinitionWithDefaults(): void
    {
        $jsonData = [
            'name' => 'minimal_field',
            'type' => FieldType::INTEGER->value,
            'value' => 42,
            'nativeType' => 'int'
        ];

        $fieldDefinition = FieldDefinition::fromJson($jsonData);

        self::assertEquals('minimal_field', $fieldDefinition->getName());
        self::assertEquals(FieldType::INTEGER, $fieldDefinition->getType());
        self::assertEquals(42, $fieldDefinition->getValue());
        self::assertEquals('int', $fieldDefinition->getNativeType());
        self::assertFalse($fieldDefinition->getNullable()); // Default value
        self::assertNull($fieldDefinition->getExtra()); // Default value
    }

    public function testJsonSerialize_returnsAllProperties(): void
    {
        $fieldDefinition = FieldDefinition::builder()
            ->name('serialize_test')
            ->type(FieldType::DATETIME)
            ->value('2023-01-01 12:00:00')
            ->nativeType('datetime')
            ->nullable(false)
            ->extra('auto_increment')
            ->build();

        $serialized = $fieldDefinition->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertEquals('serialize_test', $serialized['name']);
        self::assertEquals(FieldType::DATETIME, $serialized['type']);
        self::assertEquals('2023-01-01 12:00:00', $serialized['value']);
        self::assertEquals('datetime', $serialized['nativeType']);
        self::assertFalse($serialized['nullable']);
        self::assertEquals('auto_increment', $serialized['extra']);
    }

    public function testJsonSerialize_withNullValues_returnsNullValues(): void
    {
        $fieldDefinition = FieldDefinition::builder()
            ->name('null_test')
            ->type(FieldType::STRING)
            ->value(null)
            ->nativeType('varchar')
            ->nullable(true)
            ->extra(null)
            ->build();

        $serialized = $fieldDefinition->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertEquals('null_test', $serialized['name']);
        self::assertEquals(FieldType::STRING, $serialized['type']);
        self::assertNull($serialized['value']);
        self::assertEquals('varchar', $serialized['nativeType']);
        self::assertTrue($serialized['nullable']);
        self::assertNull($serialized['extra']);
    }
} 