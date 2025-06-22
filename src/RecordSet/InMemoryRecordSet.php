<?php

namespace Amtgard\ActiveRecordOrm\RecordSet;

use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\Traits\Builder\Builder;

class InMemoryRecordSet implements RecordSet
{
    private array $__records;
    private array $__pdoDefinition;
    private array $__fieldDefinition;
    private int $index = -1;

    public function __construct(string $jsonRecordSet) {
        $deserialized = json_decode($jsonRecordSet, true);
        $this->__records = $deserialized['records'] ?? [];
        $this->__pdoDefinition = $deserialized['pdoDefinition'];
        $this->__fieldDefinition = [];
        foreach ($deserialized['fieldDefinition'] as $field) {
            $this->__fieldDefinition[] = FieldDefinition::fromJson($field);
        }
    }

    public function getDefinition(): array
    {
        return $this->__fieldDefinition;
    }

    public function getPdoDefinition(): array
    {
        return $this->__pdoDefinition;
    }

    public function getRecord(): array|null
    {
        return $this->__records[$this->index] ?? null;
    }

    public function next(): bool
    {
        if ($this->index < count($this->__records)) {
            $this->index++;
        }
        return $this->index < count($this->__records);
    }

    public function size(): int
    {
        return count($this->__records);
    }

    public function hasActiveRecord(): bool
    {
        return isset($this->__records[$this->index]);
    }

    public function __get(string $field): mixed
    {
        return $this->__records[$this->index][$field] ?? null;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'records' => $this->__records,
            'pdoDefinition' => $this->getPdoDefinition(),
            'fieldDefinition' => $this->getDefinition()
        ];
    }

    public function hasField(string $field): bool
    {
        return is_array($this->__records) && is_array($this->__records[$this->index]) && array_key_exists($field, $this->__records[$this->index]);
    }
}