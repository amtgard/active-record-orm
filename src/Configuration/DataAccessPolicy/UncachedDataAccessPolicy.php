<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;

class UncachedDataAccessPolicy implements DataAccessPolicy
{

    private Database $database;

    public function __construct(Database $database, ActiveRecordOrmConfiguration $configuration) {
        $this->database = $database;
    }

    public function applyTableSchemaPolicy(string $name): TableSchema
    {
        return UncachedTableSchema::builder()
            ->tableName($name)
            ->database($this->database)
            ->build();
    }

    public function applyQueryPolicy(Query $query): RecordSet
    {
        $result = $this->database->executeQuery($query);
        $query->postQuery();
        return $result;
    }
}