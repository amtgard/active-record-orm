<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;

class TableSchema
{
    private Database $database;
    private string $tableName;
    private FieldDefinition $primaryKey;

    /** @var FieldDefinition[]ß */
    private array $columns = [];

    public function __construct(Database $database, string $tableName) {
        $this->database = $database;
        $this->tableName = $tableName;
    }

    public function fromJson(string $json) {

    }

    public function getFields(): array {
        return $this->columns;
    }

    public function getTableName(): string {
        return $this->tableName;
    }

    public function getPrimaryKey(): FieldDefinition {
        return $this->primaryKey;
    }

}