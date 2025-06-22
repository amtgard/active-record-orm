<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Interface\QueryCachePolicy;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;

class UncachedQueryCachePolicy implements QueryCachePolicy
{

    private Database $database;

    public function __construct(Database $database, ActiveRecordOrmConfiguration $configuration) {
        $this->database = $database;
    }

    public function buildTableSchema(string $name): TableSchema
    {
        return UncachedTableSchema::builder()
            ->tableName($name)
            ->database($this->database)
            ->build();
    }

    public function buildRecordSet(Query $query): RecordSet
    {
        return new RecordSet\PdoRecordSet($buildData[0]);
    }
}