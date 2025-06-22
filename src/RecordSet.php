<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;

interface RecordSet extends \JsonSerializable
{
    /**
     * @return FieldDefinition[]
     */
    public function getDefinition(): array;

    public function getPdoDefinition(): array;

    public function getRecord(): array|null;

    public function next(): bool;

    public function size(): int;

    public function hasActiveRecord(): bool;

    public function hasField(string $field): bool;

    public function __get(string  $field): mixed;

}