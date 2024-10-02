<?php

namespace Amtgard\ActiveRecordOrm\Interface\Table;

class FieldDefinition
{
    private string $name;
    private string $alias;
    private mixed $value;
    private FieldType $type;
    private string $nativeType;

    public function __construct(array $pdoDefinition, mixed $value, string $alias = null)
    {
        $this->name = $pdoDefinition['name'];
        $this->alias = is_null($alias) ? $pdoDefinition['name'] : $alias;
        $this->type = FieldType::fromPdoType($pdoDefinition['pdo_type'], $pdoDefinition['native_type']);
        $this->value = $value;
        $this->nativeType = $pdoDefinition['native_type'];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getType(): FieldType
    {
        return $this->type;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getNativeType(): string {
        return $this->nativeType;
    }

}