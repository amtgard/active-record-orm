<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;
use FuzzyWuzzy\Fuzz;
use FuzzyWuzzy\Process;
use Optional\Optional;

abstract class TableSchema extends Schema
{
    use Builder;
    use Getter;

    protected Database $database;
    protected string $tableName;
    protected FieldDefinition $primaryKey;

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