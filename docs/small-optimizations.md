# Small optimizations (post–latency stack)

Branch: `small-optimizations` (stacks on `latency/l05-l06-field-map-types`).

These changes trim fixed overhead on hot paths. They do **not** introduce query-result caching or entity instance caching.

## What changed

1. **`Table::clear()` reuses `TableSchema`** — `buildQueryBuilder()` receives the schema instance already held on the table, so `UncachedDataAccessPolicy` does not run `DESCRIBE` on every `clear()` (for example in `point_fetch_50` loops). This is **in-process reuse of metadata**, not a distributed cache.

2. **`PdoRecordSet` normalizes each fetched row to name keys** — After `fetch()`, numeric PDO keys are stripped so callers see one value per column (same effect as assoc fetch for typical MySQL drivers, without breaking DESCRIBE-style reads).

3. **Narrow read bind when `select()` is used** — `mapRecordForRead()` binds only selected columns plus the primary key. `save()` still materializes whatever was read; partial selects imply partial upserts (same as before when the row only contained those columns).

4. **No row or entity cache (explicitly out of scope)** — Identical primary-key `find()` still executes SQL every time. `EntityManager::register()` remains the existing session identity map for shutdown `persistAll`; this branch does not add cross-request entity caching or stale-prone query memoization. Use `CachedDataAccessPolicy` when you want **schema document** caching (C-03+), not for hydrating entities.

## Caching already in the library (unchanged here)

| Mechanism | What is cached | Stale risk |
|-----------|----------------|------------|
| `CachedDataAccessPolicy` memo + PSR-16 | `TableSchema` / JSON codec | TTL + invalidation policy; not row data |
| `RepositoryEntity::sharedEntityFieldMap()` | Reflection field map per class | Immutable for the process |
| `EntityManager` identity map | Entity instances by PK for persist | Same request; caller must not assume fresh DB reads |

Entity hydration always reflects the latest `find()`/`next()` for that table instance unless the application holds onto old entity references.
