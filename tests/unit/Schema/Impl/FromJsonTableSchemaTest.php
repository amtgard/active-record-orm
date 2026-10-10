<?php

namespace Tests\Unit\Schema\Impl;

use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchemaCodec;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Tests\Util\Constants;

class FromJsonTableSchemaTest extends AmtgardTestCase
{
    public function testRoundTrip_hydratesDescribedSchemaThroughCodec(): void
    {
        $database = Phake::mock(Database::class);
        $uncached = $this->describedSchema($database, 'integ');
        $codec = TableSchemaCodec::builder()->build();
        $encoded = $codec->encode($uncached);
        $raw = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['table', 'primaryKey', 'fields'], array_keys($raw));
        self::assertSame('integ', $raw['table']);
        self::assertSame('id', $raw['primaryKey']);
        self::assertTrue(array_is_list($raw['fields']));
        self::assertCount(14, $raw['fields']);
        foreach ($raw['fields'] as $field) {
            self::assertSame(['name', 'type', 'nativeType', 'nullable', 'extra'], array_keys($field));
            self::assertIsInt($field['type']);
            self::assertIsBool($field['nullable']);
            self::assertArrayNotHasKey('value', $field);
        }
        self::assertArrayNotHasKey('database', $raw);

        $decoded = $codec->decode($encoded);
        $hydrated = FromJsonTableSchema::builder()
            ->decodedFields($decoded['fields'])
            ->primaryKeyName($decoded['primaryKey'])
            ->tableName($decoded['table'])
            ->database($database)
            ->build();

        self::assertSame($database, $hydrated->getDatabase());
        self::assertSame('integ', $hydrated->getTableName());
        self::assertSame('id', $hydrated->getPrimaryKey()->getName());
        self::assertCount(14, $hydrated->getFields());
        self::assertSame(array_keys($uncached->getFields()), array_keys($hydrated->getFields()));
        foreach ($uncached->getFields() as $name => $field) {
            $copy = $hydrated->getField($name);
            self::assertSame($field->getName(), $copy->getName());
            self::assertSame($field->getType(), $copy->getType());
            self::assertSame($field->getNativeType(), $copy->getNativeType());
            self::assertSame($field->getNullable(), $copy->getNullable());
            self::assertSame($field->getExtra(), $copy->getExtra());
        }
        self::assertFalse($hydrated->getField('id')->getNullable());
        self::assertSame('auto_increment', $hydrated->getField('id')->getExtra());
        self::assertTrue($hydrated->getField('string_value')->getNullable());
        self::assertFalse($hydrated->getField('number_enum')->getNullable());
        Phake::verify($database, Phake::times(1))->execute('describe integ');
    }

    private function describedSchema(Database $database, string $tableName): UncachedTableSchema
    {
        $statement = Phake::mock(\PDOStatement::class);
        $recordSet = new RecordSet\PdoRecordSet($statement);
        $columns = json_decode(Constants::$DESCRIBE_TABLE_INTEG, true);
        $fetch = Phake::when($statement)->fetch();
        foreach ($columns as $column) {
            $fetch = $fetch->thenReturn($column);
        }
        $fetch->thenReturn(false);
        Phake::when($database)->execute("describe {$tableName}")->thenReturn($recordSet);

        return UncachedTableSchema::builder()
            ->tableName($tableName)
            ->database($database)
            ->build();
    }
}
