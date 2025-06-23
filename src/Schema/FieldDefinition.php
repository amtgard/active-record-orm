<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;

class FieldDefinition implements \JsonSerializable
{
    use Builder, Data;

    private string $name;
    private mixed $value;
    private FieldType $type;
    private string $nativeType;
    private bool $nullable;
    private ?string $extra;

    public static function fromColumnMetadata(array $pdoDefinition, mixed $value): FieldDefinition
    {
        return FieldDefinition::builder()
            ->name($pdoDefinition['name'])
            ->type(FieldType::fromPdoType($pdoDefinition['pdo_type'], $pdoDefinition['native_type']))
            ->value($value)
            ->nativeType($pdoDefinition['native_type'])
            ->extra(null)
            ->nullable(self::pdoColumnIsNullable($pdoDefinition))
            ->build();
    }

    private static function pdoColumnIsNullable(array $pdoDefinition) {
        foreach ($pdoDefinition['flags'] as $flag) {
            if ($flag === 'not_null') {
                return false;
            }
        }
        return true;
    }

    public static function fromDescribeTable(RecordSet $fieldRecord): FieldDefinition {
        return FieldDefinition::builder()
            ->name($fieldRecord->Field)
            ->type(FieldType::fromTableType($fieldRecord->Type))
            ->nativeType($fieldRecord->Type)
            ->extra($fieldRecord->Extra)
            ->nullable($fieldRecord->Null === 'YES')
            ->build();
    }

    public static function fromJson(array $json): FieldDefinition {
        return FieldDefinition::builder()
            ->name($json['name'])
            ->type(FieldType::from($json['type']))
            ->value($json['value'])
            ->nativeType($json['nativeType'])
            ->nullable($json['nullable'] ?? false)
            ->extra($json['extra'])
            ->build();
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}