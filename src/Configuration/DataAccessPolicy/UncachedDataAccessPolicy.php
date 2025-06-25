<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;

class UncachedDataAccessPolicy implements DataAccessPolicy
{
    use Builder;

    private Database $database;

    private function __construct() {

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