<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Exception\SchemaCodecException;
use Amtgard\Traits\Builder\Builder;

/** Codec */
class TableSchemaCodec
{
    use Builder;

    private function __construct()
    {
    }

    public function encode(TableSchema $schema): string
    {
        $fields = [];
        foreach ($schema->getFields() as $field) {
            $fields[] = $this->encodeField($field);
        }
        return json_encode(
            [
                'table' => $schema->getTableName(),
                'primaryKey' => $schema->getPrimaryKey()->getName(),
                'fields' => $fields,
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array{table: string, primaryKey: string, fields: array<string, FieldDefinition>}
     */
    public function decode(string $document): array
    {
        $decoded = $this->decodeDocument($document);
        $fields = $this->decodeFields($decoded['fields']);
        $this->assertPrimaryKeyField($decoded['primaryKey'], $fields);
        return [
            'table' => $decoded['table'],
            'primaryKey' => $decoded['primaryKey'],
            'fields' => $fields,
        ];
    }

    private function encodeField(FieldDefinition $field): array
    {
        return [
            'name' => $field->getName(),
            'type' => $field->getType()->value,
            'nativeType' => $field->getNativeType(),
            'nullable' => $field->getNullable(),
            'extra' => $field->getExtra(),
        ];
    }

    private function decodeDocument(string $document): array
    {
        try {
            $decoded = json_decode($document, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SchemaCodecException('schema document is not valid JSON', previous: $exception);
        }
        if (!is_array($decoded)) {
            throw new SchemaCodecException('schema document must be a JSON object');
        }
        $this->assertPresentString(
            $decoded,
            'table',
            'schema document is missing table',
            'schema document table must be a string'
        );
        $this->assertPresentString(
            $decoded,
            'primaryKey',
            'schema document is missing primaryKey',
            'schema document primaryKey must be a string'
        );
        $this->assertPresentArray(
            $decoded,
            'fields',
            'schema document is missing fields',
            'schema document fields must be an array'
        );
        return $decoded;
    }

    private function assertPresentString(array $decoded, string $key, string $missing, string $wrongType): void
    {
        if (!array_key_exists($key, $decoded)) {
            throw new SchemaCodecException($missing);
        }
        if (!is_string($decoded[$key])) {
            throw new SchemaCodecException($wrongType);
        }
    }

    private function assertPresentArray(array $decoded, string $key, string $missing, string $wrongType): void
    {
        if (!array_key_exists($key, $decoded)) {
            throw new SchemaCodecException($missing);
        }
        if (!is_array($decoded[$key])) {
            throw new SchemaCodecException($wrongType);
        }
    }

    /**
     * @param array<mixed> $fieldDocuments
     * @return array<string, FieldDefinition>
     */
    private function decodeFields(array $fieldDocuments): array
    {
        $fields = [];
        foreach ($fieldDocuments as $fieldDocument) {
            $field = $this->decodeField($fieldDocument);
            $fields[$field->getName()] = $field;
        }
        return $fields;
    }

    private function decodeField(mixed $fieldDocument): FieldDefinition
    {
        if (!is_array($fieldDocument)) {
            throw new SchemaCodecException('schema document field must be an object');
        }
        try {
            return FieldDefinition::fromJson($fieldDocument);
        } catch (\Throwable $exception) {
            throw new SchemaCodecException('schema document field could not be decoded', previous: $exception);
        }
    }

    /**
     * @param array<string, FieldDefinition> $fields
     */
    private function assertPrimaryKeyField(string $primaryKey, array $fields): void
    {
        if (!isset($fields[$primaryKey])) {
            throw new SchemaCodecException('schema document primaryKey is not a field');
        }
    }
}
