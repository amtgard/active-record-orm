<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Exception\SchemaCodecException;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchemaCodec;
use Amtgard\PHPUnit\AmtgardTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TableSchemaCodecTest extends AmtgardTestCase
{
    public function testEncode_preservesNullExtraAndOmitsDatabaseAndValue(): void
    {
        $field = FieldDefinition::builder()
            ->name('id')
            ->type(FieldType::INTEGER)
            ->nativeType('int')
            ->nullable(true)
            ->extra(null)
            ->value('not-cached')
            ->build();
        $schema = FromJsonTableSchema::builder()
            ->decodedFields(['id' => $field])
            ->primaryKeyName('id')
            ->tableName('widgets')
            ->build();

        $encoded = $this->codec()->encode($schema);
        $raw = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['table', 'primaryKey', 'fields'], array_keys($raw));
        self::assertSame('widgets', $raw['table']);
        self::assertSame('id', $raw['primaryKey']);
        self::assertTrue(array_is_list($raw['fields']));
        self::assertSame(['name', 'type', 'nativeType', 'nullable', 'extra'], array_keys($raw['fields'][0]));
        self::assertNull($raw['fields'][0]['extra']);
        self::assertTrue($raw['fields'][0]['nullable']);
        self::assertSame(FieldType::INTEGER->value, $raw['fields'][0]['type']);
        self::assertArrayNotHasKey('database', $raw);
        self::assertArrayNotHasKey('value', $raw['fields'][0]);

        $decoded = $this->codec()->decode($encoded);
        self::assertSame('widgets', $decoded['table']);
        self::assertSame('id', $decoded['primaryKey']);
        self::assertNull($decoded['fields']['id']->getExtra());
        self::assertTrue($decoded['fields']['id']->getNullable());
        self::assertNull($decoded['fields']['id']->getValue());
        self::assertSame(FieldType::INTEGER, $decoded['fields']['id']->getType());
    }

    public function testEncode_throwsWhenTheDocumentIsNotUtf8(): void
    {
        $field = FieldDefinition::builder()
            ->name('id')
            ->type(FieldType::STRING)
            ->nativeType("\xB1\x31")
            ->nullable(false)
            ->extra(null)
            ->build();
        $schema = FromJsonTableSchema::builder()
            ->decodedFields(['id' => $field])
            ->primaryKeyName('id')
            ->tableName('broken')
            ->build();

        self::assertThrows(\JsonException::class, fn () => $this->codec()->encode($schema));
    }

    public function testDecode_ignoresDatabaseKey(): void
    {
        $document = json_encode([
            'database' => [],
            'table' => 'widgets',
            'primaryKey' => 'id',
            'fields' => [[
                'name' => 'id',
                'type' => FieldType::INTEGER->value,
                'nativeType' => 'int',
                'nullable' => false,
                'extra' => 'auto_increment',
            ]],
        ]);

        $decoded = $this->codec()->decode($document);

        self::assertSame('widgets', $decoded['table']);
        self::assertSame('id', $decoded['primaryKey']);
        self::assertSame('auto_increment', $decoded['fields']['id']->getExtra());
        self::assertFalse($decoded['fields']['id']->getNullable());
    }

    #[DataProvider('invalidDocuments')]
    public function testDecode_rejectsInvalidDocument(string $document, string $message): void
    {
        try {
            $this->codec()->decode($document);
            self::fail('decode accepted an invalid schema document');
        } catch (SchemaCodecException $exception) {
            self::assertSame($message, $exception->getMessage());
            if ($message === 'schema document is not valid JSON') {
                self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
            }
            if ($message === 'schema document field could not be decoded') {
                self::assertNotNull($exception->getPrevious());
            }
        }
    }

    public static function invalidDocuments(): array
    {
        $field = [
            'name' => 'id',
            'type' => FieldType::INTEGER->value,
            'nativeType' => 'int',
            'nullable' => false,
            'extra' => null,
        ];
        return [
            'truncated' => ['{', 'schema document is not valid JSON'],
            'json null' => ['null', 'schema document must be a JSON object'],
            'json true' => ['true', 'schema document must be a JSON object'],
            'json number' => ['1', 'schema document must be a JSON object'],
            'json string' => ['"schema"', 'schema document must be a JSON object'],
            'empty object' => ['{}', 'schema document is missing table'],
            'json array' => ['[]', 'schema document is missing table'],
            'missing table' => [json_encode([
                'primaryKey' => 'id',
                'fields' => [],
            ]), 'schema document is missing table'],
            'table wrong type' => [json_encode([
                'table' => null,
                'primaryKey' => 'id',
                'fields' => [],
            ]), 'schema document table must be a string'],
            'missing primaryKey' => [json_encode([
                'table' => 'integ',
                'fields' => [],
            ]), 'schema document is missing primaryKey'],
            'primaryKey null' => [json_encode([
                'table' => 'integ',
                'primaryKey' => null,
                'fields' => [],
            ]), 'schema document primaryKey must be a string'],
            'legacy primaryKey object' => [json_encode([
                'table' => 'integ',
                'primaryKey' => ['name' => 'id'],
                'fields' => [],
            ]), 'schema document primaryKey must be a string'],
            'missing fields' => [json_encode([
                'table' => 'integ',
                'primaryKey' => 'id',
            ]), 'schema document is missing fields'],
            'fields null' => [json_encode([
                'table' => 'integ',
                'primaryKey' => 'id',
                'fields' => null,
            ]), 'schema document fields must be an array'],
            'fields string' => [json_encode([
                'table' => 'integ',
                'primaryKey' => 'id',
                'fields' => 'nope',
            ]), 'schema document fields must be an array'],
            'field scalar' => [json_encode([
                'table' => 'integ',
                'primaryKey' => 'id',
                'fields' => [1],
            ]), 'schema document field must be an object'],
            'field type unknown' => [json_encode([
                'table' => 'integ',
                'primaryKey' => 'id',
                'fields' => [array_merge($field, ['type' => 999])],
            ]), 'schema document field could not be decoded'],
            'field nativeType wrong type' => [json_encode([
                'table' => 'integ',
                'primaryKey' => 'id',
                'fields' => [array_merge($field, ['nativeType' => ['bad']])],
            ]), 'schema document field could not be decoded'],
            'primaryKey not a field' => [json_encode([
                'table' => 'integ',
                'primaryKey' => 'missing',
                'fields' => [$field],
            ]), 'schema document primaryKey is not a field'],
        ];
    }

    private function codec(): TableSchemaCodec
    {
        return TableSchemaCodec::builder()->build();
    }
}
