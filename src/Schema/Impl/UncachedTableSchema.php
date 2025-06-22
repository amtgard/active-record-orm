<?php

namespace Amtgard\ActiveRecordOrm\Schema\Impl;

use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\PostInit;

class UncachedTableSchema extends TableSchema
{

    #[PostInit]
    private function postInit() {
        $this->database->clear();
        $tableDef = $this->database->execute("describe {$this->tableName}");
        $fields = [];
        while ($tableDef->next()) {
            $field = FieldDefinition::fromDescribeTable($tableDef);
            if ($tableDef->Key === 'PRI') {
                $this->primaryKey = $field;
            }
            $fields[$field->getName()] = $field;
        }
        $this->fields = $fields;
    }
}