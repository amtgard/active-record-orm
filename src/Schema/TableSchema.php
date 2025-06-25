<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Utility\Constants;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;

abstract class TableSchema extends Schema
{
    use Builder;
    use Getter;

    protected Database $database;
    protected string $tableName;
    protected FieldDefinition $primaryKey;

    protected function getSuggestionMesssage(string $name, string $suggestion): string {
        return sprintf(Constants::$TABLESCHEMA_FIELD_MISS_ERROR, $name, $this->tableName, $suggestion);
    }

    public function getTableName(): string {
        return $this->tableName;
    }

    public function getPrimaryKey(): FieldDefinition {
        return $this->primaryKey;
    }

    public function primaryKeyIsSet(FieldSet $fieldSet): bool {
        return in_array($this->primaryKey->getName(), $fieldSet->getFieldNames());
    }

}