<?php

namespace Amtgard\ActiveRecordOrm\Interface\Table;

interface Table
{
    public function getTableDefinition(): TableDefinition;

    public function clear(): void;

    public function save(): bool;

    public function find(): int;

    public function next(): bool;

    public function op()
}