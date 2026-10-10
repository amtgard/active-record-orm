# Query and entity enrichment latency

Status: assessment, October 2026. The rankings below are structural. They say what work repeats, and on what scale. They do not say what fraction of wall clock that work is. Nothing here has been rewritten. Schema caching is a separate design in [caching-strategy.md](caching-strategy.md).

The complaint from systems that use this ORM is latency around row enrichment: a basic query returns, and turning those rows into entities is slow. This document traces that path and separates database round trips from PHP work that runs after the rows are in process.

## Read path

A multi-row repository read is `clear` (often) → `find` → `next` per row → `getCurrent` or `fetch`.

`EntityMapper::query()` / `execute()` is a thinner sibling. In query mode, `EntityMapper::next` advances the record set and does not enter `Table::next`, so it skips the per-column active-record bind. `getEntity()` still copies the current row. There is no eager join and no per-row fetch of a related entity on this path. `EntityReference` stores the foreign key on the backing property and returns. An N+1 appears only when application code loads each reference afterward.

`EntityTrait::mapEntity` is a second converter with the same shape: per-instance reflection, `class_implements`, and `DateTime` parsing. `Repository::getCurrent` does not use it.

## Pipeline

| Phase | What runs | Where | Scales |
|---|---|---|---|
| Schema load | `UncachedTableSchema::postInit` runs `describe {table}` after `Database::clear()` | `src/Schema/Impl/UncachedTableSchema.php` | Once per schema build, not per row. `TableFactory::build` resolves twice. `Table::clear` builds a new query builder and resolves again. `AuditTableFactory::build` resolves the source table twice and the audit table four times. `AuditTable::clear` clears both inner tables. |
| SQL compile | `QueryBuilder::find` builds select, from, where, order, and limit through the Builder trait | `src/Query/QueryBuilder.php`, `src/Query/Builder/FindStatementBuilder.php` | Per query |
| SQL execute | `UncachedDataAccessPolicy::applyQueryPolicy` → `Database::execute` (`prepare`, bind, `execute`) | `src/Repository/Database.php` | Per query. `MysqlPdoProvider` sets `ATTR_EMULATE_PREPARES` false. `DatabaseConfiguration` sets `MYSQL_ATTR_INIT_COMMAND` only. MySQL buffering stays at the driver default, so `execute` usually transfers the whole result. |
| Row fetch | `PdoRecordSet::next` → `PDOStatement::fetch` | `src/RecordSet/PdoRecordSet.php` | Per row. After a buffered execute this is local. Nothing calls `setFetchMode`, so the default is `FETCH_BOTH`: each row is both a numeric and an associative array. A failed fetch swallows `PDOException` and returns false. |
| Row bind | `Table::next` clears the field set and `FieldSet::mapRecord` calls `setFieldValue` for every schema field, then copies each `FieldOperation` onto the query builder | `src/Table.php`, `src/Schema/FieldSet.php` | Per row, times every column from `DESCRIBE`, including columns the `SELECT` did not ask for. `select()` changes the SQL text only. The same bind runs when `next()` returns false at the end of the set. |
| Field lookup | `Schema::hasField` | `src/Schema/Schema.php` | Once per `setFieldValue`. Again per repository-field write, because that write lands on `Entity::__set`. |
| Entity hydrate | `EntityMapper::getEntity` builds an `Entity`. `postInit` copies `ResultSet::getFieldMap`. `EntityManager::register` looks up the identity map and may discard the instance it just built | `src/Entity/EntityMapper.php`, `src/Entity/Entity.php`, `src/ResultSet.php`, `src/EntityManager.php` | Per `getEntity` call. `getFieldMap` walks the schema and reads the PDO row. It does not call `hasField`. |
| Repository convert | `Repository::getCurrent` / `fetch` → `RepositoryEntity::toRepositoryEntity` | `src/Entity/Repository/Repository.php`, `src/Entity/Repository/RepositoryEntity.php` | Per entity |
| Field-map reflection | `buildEntityFieldMap` reflects every private and protected property and its attributes | `RepositoryEntity::preInit` | Once per repository instance in that object's `postInit`, and again on every `toRepositoryEntity` because `preInit` rebuilds the map before properties are set |
| Type conversion | `fieldTypeConversions`: `DateTime`, `BackedEnum::tryFrom`, entity-reference backing key, then assignment through `Entity::__set` | `RepositoryEntity.php`, `src/Entity/Entity.php` | Per mapped property per entity |

`EntityMapper::fetch($id)` and `fetchBy` call `Table::clear` first. A loop of `fetch($id)` is one `DESCRIBE` plus one `SELECT` per id. A single `find` of N rows pays the `DESCRIBE` once, if something cleared, then one `SELECT`.

## Ranked issues

Totals from the opt-in profiler are inclusive. `schema_has_field` sits inside `field_set_value` and `row_map`, and again inside `repository_convert`.

### 1. A successful `hasField` still does a fuzzy match and builds an exception

Proven that it runs on every lookup. Highest expected PHP cost. Its share of wall clock versus the `SELECT` has to be measured.

`Optional::orElseThrow` takes an exception object, not a supplier. PHP builds that argument before the call. On a hit, the exception is discarded.

```php
return Optional::ofNullable($this->getField($name))
    ->map(fn($f) => true)
    ->orElseThrow(new \InvalidArgumentException(
        $this->getSuggestionMesssage($name, $this->suggestField($name))
    ));
```

`suggestField` builds a `Fuzz` and a `Process` and runs `extractOne`, which scores the name against every column with `weightedRatio`. `Table::next` hits this once per schema column. Repository assignment hits it again: the write reaches `Entity::__set`, and `__set` calls `hasField` before the equality check.

The cost shape is rows × columns fuzzy passes, and each pass scores every column name. Exception construction also captures a stack trace. Which of those two dominates has to be timed. A later probe can split `suggestField` from `new InvalidArgumentException` inside that method.

`ResultSet::__get` and `FieldSet::setFieldOperation` call `hasField` the same way. `getFieldMap` does not.

### 2. A `FieldOperation` is built for every column of every row

Proven. High expected impact. The share of `row_map` left after issue 1 has to be measured.

`FieldSet::setFieldValue` calls `FieldOperation::builder()->field()->value()->operation()->build()`. Builder construction reflects the class and scans for `PreInit`, each setter scans for `OnSet`, and `build()` scans for `PostInit`. `Table::next` then stores that object on the query builder. This is how the active record turns the current row into the field set for the next write. It runs when the caller only reads. It also runs once more when `next()` returns false.

`select()` does not narrow the walk. `mapRecord` iterates `Schema::getFields()`, which is the full `DESCRIBE` list.

### 3. Every repository entity re-reflects its field map and setter path

Proven. High impact for `getCurrent` and `fetch`. Absent for a raw `Entity` from `getEntity()`. Compare `field_map_reflection` with `repository_convert` before changing it.

`toRepositoryEntity` builds a new instance. Builder runs `preInit` before properties are set, so each row:

- calls `md5(microtime())` for `entityMapperEntityId`
- calls `buildEntityFieldMap()` (`ReflectionClass`, every private and protected property, every attribute, a `MappedInfo` builder per mapped field)
- reflects `EntityOf` in `getEntityMapperAttributeValue` when the mapper is not already set
- builds an empty internal `Entity` through `EntityFactory` when `entity` is not already set, which the later `->entity($hydrated)` assignment replaces

The repository object's own `postInit` already built this map once. `toRepositoryEntity` does not reuse it.

Each property then goes through `fieldTypeConversions`. Non-date fields call `class_exists`, `interface_exists`, and `class_implements` to detect enums and entity references. Assignments that reach `Entity::__set` call `hasField` again. That is the second fuzzy pass in issue 1.

### 4. `clear` and `fetch` pay an extra `DESCRIBE`

Proven as a round trip. One extra RTT per query. This dominates small result sets and high query rates. Compare it with the `SELECT` before treating it as the main cost of a wide result.

`UncachedTableSchema::postInit` always executes `describe {$tableName}`. `Table::clear` replaces the query builder, which calls `applyTableSchemaPolicy` again. The schema-cache design removes this round trip with a policy memo and, when the policy object does not survive, with APCu. It does not remove issues 1–3. A cache miss still rebuilds a `FromJsonTableSchema` today, and the uncached policy never consults a cache.

### 5. The `SELECT` itself

This is the inherent database time. Per query, plus bytes × rows × columns on the wire. Compare `sql_execute` tagged `select` with `row_fetch`. If `row_fetch` is near zero and `sql_execute` holds the time, buffering already pulled the rows during `execute`.

### 6. `DateTime` parsing and enum checks

The calls are proven. They matter when many columns are dates, enums, or entity references.

`new DateTime($string)` and `DateTime::createFromFormat('U', ...)` run per such column. A destination typed as `DateTimeInterface` whose source column is neither `FieldType::DATETIME` nor `FieldType::INTEGER` becomes `new DateTime()`, which is the current time. That is a wrong value and a wasted parse.

`isEnumType` and `isEntityInterfaceClass` call `class_exists` / `interface_exists` / `class_implements` per field per row. The result does not change for the life of the class.

### 7. A second column copy, and `FETCH_BOTH`

Proven. Lower expected impact than issues 1–3.

`ResultSet::getFieldMap` walks the schema again into `Entity::$fields`. Each PDO row is a dual-key array because fetch mode is left at `FETCH_BOTH`. Allocations scale with rows × columns. This copy does not call `hasField`.

### 8. The identity map builds an entity it may discard

Proven structure. `getEntity` always constructs an `Entity`, then `EntityManager::register` returns the instance already stored for that primary key. The new object is discarded on a repeat id.

`getPrimaryKey` allocates a new `FieldDefinition` on every call. `register` calls it, and `registerEntity` calls it again. That is wasted work on repeat ids, not an extra query.

`getEntity` also treats the primary key as an `int` in `EntityManager::getEntity(?int $entityId)`. A non-int key will not hit the map the same way. That is a correctness edge, not a latency ranking.

### 9. A read can mark the entity dirty

Not confirmed as a wall-clock cost. It is a measurement item because `EntityManager` registers `persistAll` on shutdown unless `preventShutdown` is set.

`Entity::__set` records a change when the new value is not `!==` to the stored one. Native prepares often return numbers as strings. A `DateTime` written back through `valueToFieldType` can format to a different string. After `getCurrent` on a fresh row, record `isDirty()` and `getChanges()`. If the entity is dirty, time a request through shutdown. `persistAll` may issue writes the caller did not ask for, and each of those writes calls `Table::clear`, which pays issue 4 again.

### 10. Logging and null exceptions are not on the success path

No logger runs on the read path. Null source values are stored or skipped. `Table::__get` throws `ValueNotSetException` only when the name is in neither the current record nor the field set. `PdoRecordSet::next` returns false when fetch throws.

## What schema caching changes

[caching-strategy.md](caching-strategy.md) makes `clear()` resolve a table definition from local memory or APCu instead of `DESCRIBE`. That is issue 4.

It does not change `hasField`, the per-row `FieldOperation` bind, repository field-map reflection, PDO fetch mode, or type conversion. On a cache miss, a schema backend still does not remove that PHP work. On the uncached policy, the backend does not run at all.

Do not compare a cached-policy timing with an uncached-policy timing and call the difference an enrichment win. That mixes two designs.

## Measurement

Same table, same row count, same machine. Take wall clock outside the ORM as well as the phase snapshot. Run these in order.

1. **`schema_has_field` versus `sql_execute`.** One `clear`, one `find` of about 200 rows, `next` plus `getEntity` only. Expect `schema_has_field` count near rows × schema width, plus one extra map when `next()` ends. If this phase is a large fraction of wall clock, issue 1 is the first change to prototype. Split `suggestField` from exception construction in that prototype so the two costs are visible.

2. **Remainder of `row_map` after subtracting `schema_has_field`.** That remainder is `FieldOperation` and Builder reflection (issue 2). Expect about one `field_set_value` per schema column per `next`, including the trailing false `next`.

3. **The same loop with `getCurrent` instead of `getEntity`.** `repository_convert` and `field_map_reflection` should grow by about one per row. `schema_has_field` should grow again by about one per mapped property per row. This isolates issue 3 and the second fuzzy pass.

4. **`query()` + `execute()` + `next` + `getEntity` for the same SQL.** `row_map` and `field_set_value` should stay at zero. The difference from step 1 is the active-record bind, not the database.

5. **`DESCRIBE` versus `SELECT`.** Compare execute time tagged `describe` with execute time tagged `select`. A `fetch($id)` loop should show one of each per id. A single `find` should show one describe only if `clear` ran.

6. **Dirty after read.** After `getCurrent` on a fresh row, record `isDirty()` and `getChanges()`. If true, time through shutdown.

7. **Only after 1–3 are confirmed on a realistic width and N:** one allocation profile (`ReflectionClass` constructions and peak memory) is enough. A before/after uses the same N, the same schema width, and the same snapshot.

## Instrumentation

An opt-in profiler was added in the working tree and is not part of the documents commit. With it off, `enter` / `leave` return immediately. Enable with `AARO_PROFILE=1` or `QueryPathProfiler::enable()`. Read `QueryPathProfiler::snapshot()` and call `reset()` between samples.

| Phase | Recorded around |
|---|---|
| `sql_execute` | `Database::execute`. Tag `describe`, `select`, or `other`. SQL text is not stored. |
| `row_fetch` | `PdoRecordSet::next` only. In-memory record sets are not timed. |
| `row_map` | `Table::next` field-set rebuild. Absent in `query()` mode. |
| `field_set_value` | `FieldSet::setFieldValue` |
| `schema_has_field` | `Schema::hasField` |
| `entity_hydrate` | `EntityMapper::getEntity` |
| `repository_convert` | `RepositoryEntity::toRepositoryEntity` |
| `field_map_reflection` | `RepositoryEntity::buildEntityFieldMap` |

The hook does not yet split fuzzy match from exception construction inside `hasField`. `EntityTrait::mapEntity` is not timed.

Files in that uncommitted instrumentation:

- `src/Utility/QueryPathProfiler.php`
- `src/Schema/Schema.php`
- `src/Schema/FieldSet.php`
- `src/Repository/Database.php`
- `src/RecordSet/PdoRecordSet.php`
- `src/Table.php`
- `src/Entity/EntityMapper.php`
- `src/Entity/Repository/RepositoryEntity.php`
- `tests/unit/Utility/QueryPathProfilerTest.php`

## What not to change yet

Leave `hasField`, the per-row `FieldOperation` bind, the per-entity field-map reflection, PDO fetch mode, and the `DESCRIBE` inside `clear` in place until a snapshot ranks them on a realistic width and row count. The bind exists so the current row is the next write. Removing it from reads without a separate read path changes `save()` after `next()`.

Schema-cache work should stay on the cache and schema types named in the caching strategy. It should not edit `Table::clear` or the enrichment types in order to make `DESCRIBE` cheaper.
