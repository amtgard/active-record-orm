<?php

namespace Amtgard\ActiveRecordOrm\Schema\Impl;

use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\PostInit;

class FromJsonTableSchema extends TableSchema
{
    /** @var FieldDefinition[] */
    private array $decodedFields;

    private string $primaryKeyName;

    #[PostInit]
    private function postInit()
    {
        $fields = [];
        foreach ($this->decodedFields as $field) {
            $fields[$field->getName()] = $field;
            if ($field->getName() === $this->primaryKeyName) {
                $this->primaryKey = $field;
            }
        }
        $this->fields = $fields;
    }
}
