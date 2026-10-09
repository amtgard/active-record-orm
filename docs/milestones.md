# Milestones

Active queue: caching
Base branch: optimization-documents
Resume from: C-01
Stop after: C-08

The latency queue is specified below and is not part of this run.

## Gates

This is a PHP library, not a Slim app. Do not add a container, routes, or a debug endpoint.

- Stack each milestone on the previous milestone tip. C-01 branches from `optimization-documents`.
- One branch, one commit, no push.
- Full `vendor/bin/phpunit` with no `--filter`. The suite must stay green.
- Library-wide line coverage is below 95% (about 79% while the uncommitted profiler is present, and the clean documents branch is in the same range). Do not reopen unrelated classes to close that gap. Record the full-suite line percentage at the start and at the end; the end must not be lower. Every new or changed source file in the milestone must be at or above 95% line coverage.
- Infection MSI and covered MSI at or above 80% for the files this milestone added or changed. C-01 adds `infection/infection` as a dev dependency if it is absent, with `infection.json` ignored paths for `vendor/`, `tests/`, and `build/`. Do not commit `build/` or `coverage/`.
- New decision branches log at debug or info through an injected `Psr\Log\LoggerInterface`. The default is `Psr\Log\NullLogger`, so production behavior is unchanged when the caller passes nothing. A unit test asserts those log records on their own, separate from the behavior test.
- Match this library. Builder trait, private constructor, `PostInit` where the class already uses that shape. No `declare(strict_types=1)` unless the edited file already has it. `Optional` stays inside methods. New classes name their pattern in a one-line class docblock: Strategy, Composite, Adapter, Factory, or Value Object.
- Do not edit entity enrichment to make caching easier. Leave `Table::clear`, `RepositoryEntity`, `EntityTrait`, `EntityMapper`, `Entity`, `PdoRecordSet`, and `QueryPathProfiler` alone unless a milestone names the file.
- Update the milestone's progress block: branch, commit, full-suite line percentage, per-file coverage, Infection covered MSI, and which decision branches were log-tested. Check the box only after the commit exists.

## Caching queue

Design: [caching-strategy.md](caching-strategy.md).

### C-01 Lifetime policy and in-memory expiry

- [x] C-01
- Branch: `cache/c01-lifetime-policy`
- Stack on: `optimization-documents`
- Progress: branch `cache/c01-lifetime-policy`, commit HEAD. Full-suite line coverage start 78.05% (992/1271), end 87.50% (1204/1376). Per-file line coverage: CacheEnvelope 100% (3/3), CacheSubject 100% (4/4), CompositeCacheLifetimeManagementPolicy 100% (37/37), InMemoryCache 100% (46/46), SystemClock 100% (2/2), TtlCacheLifetimeManagementPolicy 100% (25/25). Clock and CacheLifetimeManagementPolicy have no executable lines. Infection MSI 97%, covered MSI 97%. Log-tested decision branches: expiry chosen, entry treated as expired.

Prove expiry without a schema-key or backend change.

- Add `psr/simple-cache` to `require`.
- Add `CacheSubject` (database name, entry kind, key id) and `CacheEnvelope` (payload string, expiry timestamp or null). Both are value objects.
- Add `CacheLifetimeManagementPolicy` with `expiry(CacheSubject): DateInterval|int|null`, `isExpired(CacheSubject, CacheEnvelope): bool`, and `onStore(CacheSubject, CacheEnvelope): void`.
- `TtlCacheLifetimeManagementPolicy` supplies one configured TTL. Default is 12 hours. `onStore` does nothing. `isExpired` compares the envelope expiry to an injected clock.
- `CompositeCacheLifetimeManagementPolicy` uses the shortest non-null TTL, is expired when any child says so, and calls `onStore` on every child.
- Add a `Clock` with `now(): int` and a system clock. Tests use a fake clock. No `sleep`.
- `InMemoryCache::set` replaces an existing key. A null TTL means no expiry. A zero or negative TTL deletes the key, per PSR-16. A positive TTL expires against the clock. `get` on an expired key returns the default and reports a miss. `has` is false for an expired key.
- Leave `getMultiple`, `setMultiple`, and `deleteMultiple` throwing `NotImplementedException`.
- Do not change `CachedDataAccessPolicy`, schema JSON, or factories.
- Log: expiry chosen, entry treated as expired. Test those records on their own.
- Update `tests/unit/Cache/InMemoryCacheTest.php`, which currently expects a second `set` to throw.

### C-02 Schema codec and FromJson hydration

- [x] C-02
- Branch: `cache/c02-schema-codec`
- Stack on: C-01 tip
- Progress: branch `cache/c02-schema-codec`, commit HEAD. Full-suite line coverage start 87.50% (1204/1376), end 88.14% (1286/1459). Per-file line coverage: TableSchemaCodec 100% (71/71), FromJsonTableSchema 100% (6/6), CachedDataAccessPolicy 100% (46/46). SchemaCodecException has no executable lines. Infection MSI 96%, covered MSI 96%. Log-tested decision branch: codec failure treated as a miss.

`FromJsonTableSchema` becomes a hydrated schema. It stops parsing JSON.

- Add a schema codec. The document is JSON with `table`, `primaryKey`, and `fields`. Each field has `name`, `type` (`FieldType` int), `nativeType`, `nullable`, and `extra`. No `Database`, no field value.
- Encode with `JSON_THROW_ON_ERROR`. Decode failure, a missing `fields` or `primaryKey`, or a `FieldType` the enum cannot build is a codec failure.
- `FieldDefinition::fromJson` is the only field decoder on this path.
- `FromJsonTableSchema` takes the decoded field list, primary key name, table name, and live `Database`. `postInit` assigns fields and the primary key. It does not call `json_decode`.
- `CachedDataAccessPolicy` stores the codec document on a miss and hydrates through the codec on a hit. A codec failure is a miss: run `UncachedTableSchema`, store a fresh document, return that schema. Keys stay as they are until C-03.
- A round-trip test encodes a real `UncachedTableSchema` and reads it back. Assert field names, types, nullability, extra, and primary key. A truncated payload takes the miss path and runs `DESCRIBE`.
- Do not add the request memo, database key, or query-cache split here.
- Log: codec failure treated as a miss. Test that record on its own.

### C-03 Database keys, memo, and query split

- [x] C-03
- Branch: `cache/c03-schema-policy`
- Stack on: C-02 tip
- Progress: branch `cache/c03-schema-policy`, commit HEAD. Full-suite line coverage start 88.14% (1286/1459), end 88.80% (1372/1545). Per-file line coverage: CachedDataAccessPolicy 100% (131/131), Database 96.55% (28/29). Infection MSI 95%, covered MSI 95%. Log-tested decision branches: memo hit, cache hit, cache miss, cache get failure, cache set failure.

- Default schema key is `amtgard_orm:{database}:schema:{table}`. Database name comes from `Database`. Add `getDatabaseName()` if no public getter exists. `schemaKeyNameSupplier` remains an override.
- Inject `CacheLifetimeManagementPolicy`. Schema `set` uses `expiry()`. `get` builds an envelope and treats `isExpired` as a miss. Call `onStore` after a successful store.
- Memoize the built `TableSchema` on the policy for the policy object's lifetime, keyed by database and table. A memo hit returns the same instance and does not call the cache.
- `TableSchema` stays immutable after `postInit`.
- `applyQueryPolicy` executes the query and returns the database record set. It does not read or write the cache. Update the tests that expect query results to be cached.
- Schema cache and query results must not share a write path.
- `get` throwing, or a corrupt document, runs `UncachedTableSchema` and still returns the schema. `set` throwing is ignored. Do not cache an exception.
- The miss path may keep `Database::clear()` inside `UncachedTableSchema`. The hit and memo paths do not clear.
- Log: memo hit, cache hit, cache miss, cache get failure, cache set failure. Test those records on their own.
- Do not add filesystem, APCu, Redis, or Memcached here.

### C-04 One schema resolve at construction

- [x] C-04
- Branch: `cache/c04-factory-dedup`
- Stack on: C-03 tip
- Progress: branch `cache/c04-factory-dedup`, commit HEAD. Full-suite line coverage start 88.80% (1372/1545), end 88.84% (1377/1550). Per-file line coverage: TableFactory 100% (15/15), AuditTableFactory 100% (37/37). Infection MSI 100%, covered MSI 100%. Log-tested decision branches: none.

- `TableFactory::build` resolves each table name once and passes that `TableSchema` into the table and the query builder.
- `AuditTableFactory::build` resolves the source table once and the audit table once, and reuses those instances for the inner tables and the outer `AuditTable`.
- `buildQueryBuilder` accepts an already resolved schema. `Table::clear` keeps calling `buildQueryBuilder` with the policy and no prebuilt schema.
- Do not change the body of `Table::clear`.
- Tests cover the single resolve: one `applyTableSchemaPolicy` per distinct table name during `build`, and a later `clear` still calls the policy.

### C-05 Filesystem adapter and backend factory

- [ ] C-05
- Branch: `cache/c05-filesystem`
- Stack on: C-04 tip
- Progress: not started

- Add a filesystem `CacheInterface`. The filename is a hash of the key under `CACHE_PATH`. The raw table name is never a path segment. Honor TTL the same way `InMemoryCache` does.
- Add `CacheBackendFactory::fromEnvironment()` in the style of `DatabaseConfiguration::fromEnvironment`. `CACHE_CONTROL=memory` returns `InMemoryCache`. `file` returns the filesystem adapter rooted at `CACHE_PATH`. `none` is represented by returning no cache so the caller builds `UncachedDataAccessPolicy`. An unknown value fails at construction.
- `InMemoryDataAccessPolicy` injects `InMemoryCache` and the default TTL policy.
- Log: backend selected. Test that record on its own.
- Do not implement APCu, Redis, or Memcached in this milestone.

### C-06 APCu adapter

- [ ] C-06
- Branch: `cache/c06-apcu`
- Stack on: C-05 tip
- Progress: not started

- Add an APCu adapter behind `CacheInterface`, using `apcu_*`, honoring the TTL supplied to `set`.
- `CACHE_CONTROL=apcu` selects it. A missing `ext-apcu` fails at construction.
- Tests use a fake APCu store or skip the extension call behind a narrow collaborator so `composer` unit tests do not require the extension. The adapter class itself still calls `apcu_*` only through that collaborator, and the collaborator's success and failure paths are tested.
- Log: APCu get failure treated as a miss. Test that record on its own.

### C-07 Redis adapter

- [ ] C-07
- Branch: `cache/c07-redis`
- Stack on: C-06 tip
- Progress: not started

- Add a Redis adapter using `ext-redis` behind a narrow client collaborator. Tests use a fake client. Do not add Predis.
- `CACHE_CONTROL=redis` selects it. A missing extension fails at construction.
- Honor TTL. A client exception on `get` is a miss. A client exception on `set` is ignored by the policy, which already has that failure behavior; the adapter reports the failure to its caller.
- Log: Redis get failure, Redis set failure. Test those records on their own.

### C-08 Memcached adapter

- [ ] C-08
- Branch: `cache/c08-memcached`
- Stack on: C-07 tip
- Progress: not started

- Add a Memcached adapter using `ext-memcached` behind a narrow client collaborator. Tests use a fake client.
- `CACHE_CONTROL=memcached` selects it. A missing extension fails at construction.
- Same TTL and failure behavior as the Redis adapter.
- Log: Memcached get failure, Memcached set failure. Test those records on their own.
- This is the last milestone in the active queue.

### C-09 Eventual consistency lifetime

- [ ] C-09
- Status: deferred. Not in the active queue. Do not start it from this run.
- Branch: `cache/c09-eventual-consistency`
- Stack on: C-08 tip
- Progress: not started

Later, compose `EventualConsistencyCacheLifetimeManagementPolicy` with a very long TTL. `onStore` publishes a refresh for that database and table through Amtgard Redis SetQueue. A worker replaces the cache document. A down queue does not fail the read. Leave `Entity\Policy\EventualConsistencyPolicy` unchanged.

## Latency queue

Design: [query-enrichment-latency.md](query-enrichment-latency.md).

Do not start this queue in the caching run. L-03 and later stay blocked until L-02 has written measured numbers into the latency document and those numbers say the issue is worth changing.

### L-01 Opt-in query path profiler

- [ ] L-01
- Branch: `latency/l01-query-profiler`
- Stack on: the caching tip when this queue starts, or `optimization-documents` if caching has not landed
- Progress: not started

A working copy of this profiler was stashed as `opt-in query path profiler` before the caching queue started. Use that stash as the starting diff. Do not rewrite it from scratch if the stash still applies.

- `QueryPathProfiler` is off unless `AARO_PROFILE=1` or `enable()` is called. Off means `enter` and `leave` return immediately and record nothing.
- Phases: `sql_execute` (tags `describe`, `select`, `other`), `row_fetch`, `row_map`, `field_set_value`, `schema_has_field`, `entity_hydrate`, `repository_convert`, `field_map_reflection`.
- Hooks stay where the latency document lists them. No behavior change on the unprofiled path.
- Unit test: off records nothing and the wrapped calls still return the same types; on records `schema_has_field` and `sql_execute` tags.
- Do not change `hasField`, the per-row bind, or schema caching.

### L-02 Record a measurement

- [ ] L-02
- Status: blocked until L-01 is on the branch and a real schema is available to time
- Branch: `latency/l02-measurement`
- Stack on: L-01 tip
- Progress: not started

Run the six scenarios in the latency document. Append a Measurement section with row count, schema width, phase counts, phase seconds, and wall clock. No production behavior change. L-03 and later read this section before they start.

### L-03 Lazy field suggestion

- [ ] L-03
- Status: blocked on L-02 showing `schema_has_field` as a large fraction of wall clock
- Branch: `latency/l03-lazy-has-field`
- Stack on: L-02 tip
- Progress: not started

`Schema::hasField` runs `suggestField` and builds the `InvalidArgumentException` only on a miss. A hit is an array lookup. Miss behavior, including the suggestion text, stays the same. Add a profiler split between suggestion and exception construction if the L-02 numbers did not already separate them.

### L-04 Read path without a per-column write bind

- [ ] L-04
- Status: blocked on L-02 showing the `row_map` remainder, after `schema_has_field`, as a large fraction of wall clock
- Branch: `latency/l04-read-bind`
- Stack on: L-03 tip
- Progress: not started

A read that only calls `next` and hydrates an entity does not build a `FieldOperation` per schema column. `save()` after `next()` still sees the current row as the write field set. `select()` narrowing is in scope only if it can be done without changing which columns `save()` writes. `query()` mode already skips this bind; keep that difference.

### L-05 Reuse the repository field map

- [ ] L-05
- Status: blocked on L-02 showing `field_map_reflection` once per `getCurrent` row
- Branch: `latency/l05-field-map`
- Stack on: L-04 tip
- Progress: not started

`toRepositoryEntity` reuses the field map and `EntityOf` resolution built for that repository class. It does not call `md5(microtime())` or build an empty internal entity that the hydrated entity immediately replaces. Per-row type conversion stays.

### L-06 Date and enum conversion

- [ ] L-06
- Status: blocked on L-02 showing date, enum, or entity-reference columns as a measurable share
- Branch: `latency/l06-type-conversion`
- Stack on: L-05 tip
- Progress: not started

Cache `class_implements` results for a destination type for the process. A `DateTimeInterface` destination whose source column is neither `DATETIME` nor `INTEGER` does not silently become `new DateTime()`. Choose an explicit failure or a null, and test it. Do not change valid datetime and integer conversions.

### L-07 Single-key PDO rows

- [ ] L-07
- Status: blocked on L-02 showing fetch allocation as worth changing
- Branch: `latency/l07-fetch-assoc`
- Stack on: L-06 tip
- Progress: not started

`PdoRecordSet` fetches `PDO::FETCH_ASSOC`. Callers that read columns by name stay green. Do not change query results.

### L-08 Identity map construction

- [ ] L-08
- Status: blocked on L-02 showing wasted `getEntity` construction on repeat ids
- Branch: `latency/l08-identity-map`
- Stack on: L-07 tip
- Progress: not started

`EntityManager::register` returns the stored entity for a primary key without a second `FieldDefinition` allocation on the hit path. A first insert still registers the new entity. Document the non-int primary key behavior rather than changing it in this milestone.

### L-09 Dirty read follow-up

- [ ] L-09
- Status: blocked on L-02 scenario 6
- Branch: `latency/l09-dirty-read`
- Stack on: L-08 tip
- Progress: not started

If a fresh `getCurrent` leaves `isDirty()` true, stop the hydration write from recording a change when the stored value and the assigned value are the same datum. If scenario 6 shows a clean entity, close this milestone as not needed and record that in the progress block. Do not remove shutdown `persistAll`.
