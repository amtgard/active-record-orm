# Schema caching strategy

Status: design, October 2026. This document is the plan. It does not describe implemented behavior except where it names the code that exists today.

The cache target is table definitions. A table definition is the column list, field types, nullability, and primary key that `DESCRIBE` returns for one table in one database. Entity field maps, query rows, and record sets are out of scope.

## Decision

`CachedDataAccessPolicy` already speaks PSR-16 (`Psr\SimpleCache\CacheInterface`). The library will keep that port and add backends behind it: process memory, filesystem, APCu, Redis, and Memcached. `psr/simple-cache` moves into this package's `require`. No Symfony Cache, no PSR-6, and no new container.

Five constraints shape the work:

1. **Lifetime is a policy, not a parameter.** Call sites do not take a TTL. An injectable `CacheLifetimeManagementPolicy` supplies expiry and invalidation for each entry. A TTL implementation proves the mechanism now. An eventual-consistency implementation comes later and composes with TTL.
2. **Keys are segregated by database.** A schema entry is named by database and table. Two databases never share a schema document.
3. **Schema load is deduplicated only where the change is local and obvious.** `Table::clear()` keeps asking the policy for a schema. Repeated asks are satisfied from local memory, and from APCu when the policy object itself does not survive the call.
4. **`FromJsonTableSchema` is a hydrated schema, not a parser.** One codec owns the document. One manager owns decode, validation, the live `Database`, and reuse of the built object.
5. **Query results stay uncached in this work.** Schema caching and query caching use different stores. Turning on a schema backend must not cache `find`, `save`, or `delete`.

Eventual consistency for cache refresh is specified here and implemented later. The existing `EventualConsistencyPolicy` is a repository persist policy. It holds a `CacheInterface` and does not use it. This design does not change that class.

## What the code does today

| Piece | Role |
|---|---|
| `Psr\SimpleCache\CacheInterface` | Port. The policy calls `get` and `set`. The interface is installed only because Phinx pulls in `cakephp/datasource`. |
| `InMemoryCache` | Only adapter. Process-local array. `set` ignores TTL and throws `NotImplementedException` on a second write to the same key. |
| `CachedDataAccessPolicy` | Cache-aside for schema and, separately, for query results. |
| `InMemoryDataAccessPolicy` | Builds an `InMemoryCache` and delegates. |
| `UncachedDataAccessPolicy` | Always builds `UncachedTableSchema`. |
| `UncachedTableSchema` | Calls `Database::clear()`, then `describe {$tableName}`. |
| `FromJsonTableSchema` | `json_decode`s a string and builds `FieldDefinition`s from `FieldDefinition::fromJson`. |
| `FileBasedActiveRecordOrmConfiguration` | Stub. `CACHE_CONTROL`, `CACHE_PATH`, and `CACHE_TABLES` are documented in the README and `test-resources/.env`. No PHP reads them. |

The default schema key is `amtgard_orm_table_schema_{$tableName}`. It has no database name. A `schemaKeyNameSupplier` callable can replace it.

On a miss the policy stores `json_encode($schema)`. `Schema::jsonSerialize` and `FieldDefinition::jsonSerialize` both return `get_object_vars($this)`, so the document includes a `database` key. `Database` has only private properties, so that key becomes `{}` and does not contain the PDO handle. `FromJsonTableSchema` ignores it and reattaches the live `Database`. The unit test that covers a hit feeds `Constants::$JSON_ENCODED_INTEG_SCHEMA` on the second `get()`. It does not assert that encoding a real `UncachedTableSchema` round-trips.

`applyQueryPolicy` uses the same `CacheInterface`. Its key is `Query::hash()` with no prefix and no TTL. `Table::find`, `count`, `save`, and `delete` all go through it. A cached write skips the database. That store must be split from the schema store before any shared backend is enabled.

### How often a schema is loaded

There is one discovery path. Entity attributes build `EntityFieldMap` by reflection. That map is not the table definition.

`TableFactory::build` calls `applyTableSchemaPolicy` for the `Table`, then `buildQueryBuilder` calls it again for the `QueryBuilder`. A cold uncached build runs `DESCRIBE` twice. `AuditTableFactory::build` resolves the source table twice and the audit table four times (`tableSchema` and `queryBuilder` for the inner audit table, then both again for the outer `AuditTable`).

`EntityManager` keeps a mapper for the life of that manager. `find`, `next`, and `save` do not re-resolve schema. `Table::clear` drops the query builder and calls `buildQueryBuilder`, which resolves schema again. `AuditTable::clear` does this for the source table and the audit table. The README treats `clear()` as the start of each query.

So schema load is once per table construction, twice because the factory and the query builder each resolve, and again on every `clear()`. It is not once per row. After the schema object is attached, row conversion reads `TableSchema::getFields()` on that object.

A cache hit still allocates a new `FromJsonTableSchema` and decodes JSON. It also skips `UncachedTableSchema`'s `Database::clear()`, which today wipes bound parameters as a side effect of `DESCRIBE`. The miss path keeps that clear. The hit path already skips it, and the memoized path skips it too.

## Lifetime policy

`CacheLifetimeManagementPolicy` is the supplier for cache invalidation. Adapters and `CachedDataAccessPolicy` ask it what to do. They do not grow TTL parameters of their own.

```php
interface CacheLifetimeManagementPolicy
{
    public function expiry(CacheSubject $subject): \DateInterval|int|null;

    public function isExpired(CacheSubject $subject, CacheEnvelope $envelope): bool;

    public function onStore(CacheSubject $subject, CacheEnvelope $envelope): void;
}
```

`CacheSubject` carries the database name, the entry kind (`schema`), and the table name. `CacheEnvelope` is the stored document plus the expiry the policy supplied when it was written. Adapters persist the envelope. `expiry` returns the TTL passed to `CacheInterface::set`, or `null` when the entry does not expire. `isExpired` lets a backend that cannot enforce TTL, and a later generation check, force a miss. `onStore` is the hook a later policy uses to enqueue work. The TTL policy's `onStore` is empty.

`CompositeCacheLifetimeManagementPolicy` holds an ordered list of policies.

- `expiry` returns the shortest non-null TTL among the children.
- `isExpired` is true when any child says the envelope is expired.
- `onStore` runs for every child.

### TTL, now

`TtlCacheLifetimeManagementPolicy` is the first implementation. It proves the mechanism: a write stores an expiry, a later read misses when that expiry has passed, and a miss loads from `DESCRIBE` and stores a new envelope.

Every adapter, including `InMemoryCache`, honors the supplied expiry. `InMemoryCache::set` replaces an existing key so a refill after expiry works. The current throw on a second `set` cannot survive this.

Schema TTL is long. Table definitions change when a migration runs, not on a request. A safety TTL of hours is enough to prove expiry, refill, and failure behavior. The concrete duration is configuration on the policy, with a default of 12 hours. `CACHE_TABLES=per-session` is not that duration. Schema is not per user. That variable stays unread until a query-cache design needs it.

### Eventual consistency, later

`EventualConsistencyCacheLifetimeManagementPolicy` is the second implementation. It is not built in the first pass.

When it exists, it is composed with a very long TTL policy. The TTL is the backstop. The consistency policy uses [Amtgard Redis SetQueue](https://github.com/amtgard/redis-set-queue) and background workers:

- `onStore` publishes a refresh for that database and table.
- A worker runs `DESCRIBE`, encodes the document, and replaces the cache entry.
- Readers keep serving the current document until the worker writes a new one, or until the long TTL expires.
- A down queue does not fail the read. The TTL refill still loads from `DESCRIBE` in process.

That policy is cache refresh. It is a different type from `Entity\Policy\EventualConsistencyPolicy`.

## Database segregation

The key is built in one place from `CacheSubject`:

```text
amtgard_orm:{database}:schema:{table}
```

`database` is `Database`'s database name, the same name `fqTableName` prefixes onto a table. The table name is the unqualified name passed to `applyTableSchemaPolicy`. A generation suffix can be added later as `amtgard_orm:{database}:schema:{generation}:{table}` without changing adapters.

`schemaKeyNameSupplier` remains an override for tests and for a host that already partitions keys. The default supplier includes the database name. A supplier that returns a key without a database segment is a deliberate opt-out, not the default.

Schema entries and any future query entries use different key prefixes and different `CacheInterface` instances. `clear()` on one store must not drop the other.

## Backends

`CachedDataAccessPolicy` stays backend-agnostic. It receives a `CacheInterface` and a `CacheLifetimeManagementPolicy`. `InMemoryDataAccessPolicy` remains the thin builder that injects `InMemoryCache` and the TTL policy.

A `fromEnvironment()` factory, in the same style as `DatabaseConfiguration::fromEnvironment`, reads `CACHE_CONTROL`:

| Value | Adapter | When it is the right local choice |
|---|---|---|
| `memory` | `InMemoryCache` | Default. Unit tests. One policy object kept for the request. |
| `file` | Filesystem adapter, rooted at `CACHE_PATH` | CLI, Phinx, and the README's current setting. The filename is a hash of the key. |
| `apcu` | APCu (`apcu_*`) | PHP-FPM on one host. Shared across workers. Keeps a schema lively for the rest of the request even when the policy object is rebuilt. Off in CLI unless `apcu.enable_cli` is set. |
| `redis` | `ext-redis` | More than one app host shares a database. Predis only when the extension cannot be loaded. |
| `memcached` | `ext-memcached` | Same shape as Redis, with nothing to persist. Optional. |
| `none` | `UncachedDataAccessPolicy` | Explicit bypass. |

Missing extension or bad config fails at construction. A later connection error is a cache miss, not a construction failure.

### Failure

- `get` throws, times out, or returns a document the codec rejects: build `UncachedTableSchema`, attempt `set`, return the schema.
- `set` throws: ignore it and return the schema just loaded.
- Do not cache an exception.
- Do not overwrite a good entry with a failed encode.
- A duplicate `DESCRIBE` is acceptable. The first version has no stampede lock.

## Managing FromJsonTableSchema

`FromJsonTableSchema` currently does three jobs: it stores a JSON string, it decodes that string, and it is the `TableSchema`. Those split.

**Document.** A schema document is a JSON object with `table`, `primaryKey`, and `fields`. Each field has `name`, `type` (the `FieldType` int), `nativeType`, `nullable`, and `extra`. The document has no `Database`, no field value, and no PDO state. Encoding is `JSON_THROW_ON_ERROR`. `get_object_vars` stops being the cache format.

**Codec.** One type encodes a `TableSchema` to that document and decodes a string back to field definitions. `json_decode` failure, a missing `fields` or `primaryKey`, or a `FieldType` the enum cannot build is a cache miss. `FieldDefinition::fromJson` is the only field decoder on this path. `fromDescribeTableJson` stays on the `DESCRIBE` path.

**Hydration.** `FromJsonTableSchema` receives the decoded field list, the primary key name, the table name, and the live `Database`. It does not accept a JSON string. `postInit` assigns fields and the primary key. It does not parse.

**Manager.** `CachedDataAccessPolicy` keeps a map of built `TableSchema` instances keyed by database and table, for the life of that policy object.

1. Memo hit: return the same instance.
2. Store hit: codec decodes, `FromJsonTableSchema` hydrates, memoize, return.
3. Miss, or `isExpired`, or a corrupt document: `UncachedTableSchema` runs `DESCRIBE`, codec encodes, lifetime policy supplies the expiry, `set` stores the envelope, memoize, return.

`TableSchema` is immutable after `postInit`. `fields` and `primaryKey` are assigned only in `UncachedTableSchema` and `FromJsonTableSchema`. Callers only read them. A memoized instance is safe to share between a `Table` and its `QueryBuilder`.

The round-trip test encodes a real `UncachedTableSchema` and reads it back through the codec into `FromJsonTableSchema`. It asserts field names, types, nullability, extra, and primary key. A truncated payload asserts a miss and a fresh `DESCRIBE`.

## Schema load duplication

Two call sites repeat work.

**Construction.** `TableFactory::build` resolves a table twice. `AuditTableFactory::build` resolves the source table twice and the audit table four times. The fix is local: resolve each distinct table name once, then pass that `TableSchema` into both the table builder and the query builder. `buildQueryBuilder` gains a schema argument used by those factories. `Table::clear` keeps calling `buildQueryBuilder` with the policy, so a new query still goes through `applyTableSchemaPolicy`.

**Each query.** `clear()` is the repeat site, and `AuditTable::clear` doubles it. That method stays as it is. The policy memo returns the instance already built. When the policy is reconstructed inside the request, APCu (or the filesystem adapter) returns the document without `DESCRIBE`, and the manager hydrates it once per policy object.

If sharing a schema instance at construction time disturbs a caller, drop that factory change and rely on the memo. Do not rewrite `Table::clear` to skip the policy.

## What this removes from the query path

Row enrichment reads the schema already hanging off the table. It does not run `DESCRIBE` per row. The repeated cost on a basic query is `clear()` resolving schema again: a `DESCRIBE`, or a JSON decode and a new `FromJsonTableSchema`, before `find` returns rows and entities are built.

A policy memo, or APCu for the lifetime of the request, makes that resolution a local map lookup. That removes the schema-load tax from the path that builds entities, including requests that only use APCu and never leave the machine.

The enrichment assessment in [query-enrichment-latency.md](query-enrichment-latency.md) ranked that tax below the per-row PHP work. A successful `Schema::hasField` still builds a fuzzy column suggestion and an exception, once per schema column per row, and again when a repository entity writes each field. Per-row `FieldOperation` construction and per-entity field-map reflection are the next PHP costs. A schema cache does not run on those paths. `DESCRIBE` on `clear()` still matters for small result sets and for `fetch($id)` loops, where it is one extra round trip per query.

Measure before and after on one request that clears a known set of tables and hydrates the rows:

- count of `DESCRIBE`
- count of `applyTableSchemaPolicy`
- time in schema resolution versus time in SQL fetch and entity conversion

If a request resolves a handful of tables once, the memo is the whole win and a remote backend adds nothing. A remote backend earns its place when MySQL is remote, or when many FPM workers each `DESCRIBE` the same tables.

## Phases

**1. Prove the mechanism.** Add `psr/simple-cache` to `require`. Add `CacheLifetimeManagementPolicy`, `TtlCacheLifetimeManagementPolicy`, and `CompositeCacheLifetimeManagementPolicy`. Segregate keys by database. Add the schema codec and make `FromJsonTableSchema` hydrate a validated document. Memoize inside `CachedDataAccessPolicy`. Make `applyQueryPolicy` execute against the database and stop writing to the schema cache. Teach `InMemoryCache::set` to replace and to honor expiry. Share one resolved schema per table inside `TableFactory` and `AuditTableFactory`. Add the APCu and filesystem adapters and the `CACHE_CONTROL` factory.

**2. Shared backends.** Add Redis and Memcached adapters behind the same factory. They consume the same envelope and the same lifetime policy.

**3. Eventual consistency.** Add `EventualConsistencyCacheLifetimeManagementPolicy`, compose it with a very long TTL, and refresh entries from Redis SetQueue workers. Leave `Entity\Policy\EventualConsistencyPolicy` alone until that persist path has its own design.

## Files

Expected edits stay on the cache and schema side.

- `src/Cache/InMemoryCache.php`, plus filesystem, APCu, Redis, and Memcached adapters beside it
- `CacheLifetimeManagementPolicy` and its TTL and composite implementations
- `src/Configuration/DataAccessPolicy/CachedDataAccessPolicy.php`
- `src/Configuration/DataAccessPolicy/InMemoryDataAccessPolicy.php`
- schema codec, `src/Schema/Schema.php`, `src/Schema/FieldDefinition.php`, `src/Schema/Impl/FromJsonTableSchema.php`
- `src/Factory/TableFactory.php`, `src/Factory/AuditTableFactory.php` for the one-resolve construction
- `composer.json`
- unit tests for the cache, the policy, the codec round-trip, and the factories
- `README.md` and `test-resources/.env` when `CACHE_CONTROL` becomes real

`Table::clear` and the entity enrichment types stay put. `src/Entity/Policy/EventualConsistencyPolicy.php` stays put.

## Non-goals

- Query-result caching, including any TTL on `Query::hash()` entries
- Changing `EventualConsistencyPolicy` persist behavior
- Caching `EntityFieldMap` reflection
- Rewriting row enrichment or type conversion
- A stampede lock
- Reading `CACHE_TABLES` as a schema scope
