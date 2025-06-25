<?php

namespace Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Cache\InMemoryCache;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Psr\SimpleCache\CacheInterface;

class InMemoryDataAccessPolicy extends CachedDataAccessPolicy
{
    use Builder;

    private Database $database;
    private CachedDataAccessPolicy $cachedDataAccessPolicy;

    #[PostInit]
    private function postInit() {
        $cache = InMemoryCache::builder()->build();
        $this->cachedDataAccessPolicy = CachedDataAccessPolicy::builder()
            ->database($this->database)
            ->cache($cache)
            ->build();
    }


    public function applyTableSchemaPolicy(string $name): TableSchema
    {
        return $this->cachedDataAccessPolicy->applyTableSchemaPolicy($name);
    }

    public function applyQueryPolicy(Query $query): RecordSet
    {
        return $this->cachedDataAccessPolicy->applyQueryPolicy($query);
    }
}