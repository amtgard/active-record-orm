<?php

namespace Amtgard\ActiveRecordOrm\Interface\Table;

use Amtgard\ActiveRecordOrm\Interface\Database\IDatabase;

interface TableDefinitionFactory
{
    public function getTableDefinition(IDatabase $database, string $tableName): TableDefinition;
}