<?php

namespace Amtgard\ActiveRecordOrm\Schema\Impl;

use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\PostInit;

class FromJsonTableSchema extends TableSchema
{
    private string $jsonDefinition;

    #[PostInit]
    private function postInit()
    {
        $this->fields = [];
        $jsonArray = json_decode($this->jsonDefinition, true);
        foreach ($jsonArray['fields'] as $field) {
            $field = FieldDefinition::fromJson($field);
            $this->fields[$field->getName()] = $field;
            if ($field->getName() == $jsonArray['primaryKey']['name']) {
                $this->primaryKey = $field;
            }
        }
    }
}