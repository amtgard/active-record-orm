<?php

namespace Amtgard\ActiveRecordOrm\Interface\Table;

interface FieldData
{
    public function listFields(): array;

    public function fieldOperation(string $field): FieldOperation;

    public function primaryKeyIsSet(): bool;


}