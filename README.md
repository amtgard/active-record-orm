# Amtgard Active Record ORM

A modern Active Record ORM for PHP 8.3+ designed for the ORK4 system. This library provides a clean, intuitive interface for database operations with support for both traditional table-based queries and entity-based object mapping.

The library includes a command-line tool (`repository.php`) for generating Repository and RepositoryEntity classes from existing MySQL schemas, creating database migration files, and managing audit logging infrastructure.

## Introduction

Amtgard Active Record ORM (Aaro) is an active record data access layer, in the vein of PorqDB - a completely dead ORM project from the dawn of time.

Aaro focuses on two basic use cases: CRUD operations with basic constraints and SQL record sets. Aaro does not offer facilities for modeling relationships or a DSL over SQL - the concept is that SQL is already the most robust language for this purpose.

Aaro provides four operating modes for database access:

1. **Low level database** - Direct active record operations and SQL queries using the `Database` class
2. **Table level access** - Active record operations and queries using the `Table` class
3. **Entity level** - Object mapping with automatic persistence using `EntityMapper` and `Entity` classes
4. **RepositoryEntity abstraction level** - High-level repository pattern with `Repository` and `RepositoryEntity` classes for type-safe, ergonomic data access

Each mode builds upon the previous, offering increasing levels of abstraction and convenience while maintaining the flexibility to drop down to lower levels when needed.

## Installation

Install via Composer:

```bash
composer require amtgard/active-record-orm
```

## Requirements

- PHP 8.3 or higher
- PDO extension
- JSON extension
- MySQL database (other databases may be supported in future versions)

### Schema Requirements

Aaro is not overly opinionated about schema design, in the sense that it allows for a "Bring Your Own Schema" design - it does not try to enforce schemas based on object models.

However, by convention **it expects exactly one primary key field per table**.

Aaro works best with primary keys defined as auto-sequencing integers.

## Basic Usage

### Setting Up Database Connection

Aaro assumes connection by convention and works with `Dotenv` for configuration.

_.env_
```dotenv
## Mysql Config

DB_HOST="127.0.0.1"
DB_PORT="24306"
DB_USER="integtest"
DB_PASS="password"
DB_NAME="integtest"

CACHE_TABLES="per-session"
CACHE_CONTROL="file"
CACHE_PATH="./table_cache"
```

Aaro is designed to work natively with aggressive caching policies, including [Amtgard Redis SetQueues](https://github.com/amtgard/redis-set-queue), which provides eventually-consistent persistence.

Basic usage can used uncached policies, including the `UncachedDataAccessPolicy` below.

```php
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;use Amtgard\ActiveRecordOrm\Factory\TableFactory;use Amtgard\ActiveRecordOrm\Repository\Database;use Dotenv\Dotenv;

// Configure database connection from local .env file
$dotenvPath = __DIR__;
$dotenv = Dotenv::createImmutable($dotenvPath);
$dotenv->safeLoad();
$config = DatabaseConfiguration::fromEnvironment();
$provider = MysqlPdoProvider::fromConfiguration($config);
$db = Database::fromProvider($provider);

// Set up data access policy
$tablePolicy = UncachedDataAccessPolicy::builder()->database($db)->build();

// Create a table instance
$itemTable = TableFactory::build($db, $tablePolicy, 'items');
```

### Basic CRUD Operations

The core of Aaro are basic CRUD operations, specifically `find()` (aka SQL `select`) and `save()` (a contextually-aware mnemonic for SQL `insert` or `update`).

Access fields of a table is done by magic setters and getters. Every field in the table will be exposed public members of table object when instantiated.

For instance, if the table `items` below has the fields `id` and `string_value`, then those fields will be exposed as public properties of the `itemTable` object.

The values of the fields of the records can be accessed by accessing the fields on the object. For instance `$total = $invoiceLine->quantity * $invoiceLine->amount;`.

Assigning values to a field (`$itemTable->string_value = "my value";`) will update the local object in memory and can be persisted to the repository by calling `$itemTalbe->save()` or persisting via the Entity Manager.

#### Update vs Insert Context

When performing updates vs inserts, the equals operator is contextually sensitive to either set a value or constraint mode.

Aaro determines the context by checking for the existence of a primary key value on the given record. If a primary key is set, then the equals operations (`$itemTable->name = "Bob"`) assumes the current context is either an update operations when `save()` is called, or an additional constraint when `find()` is called. If there is no primary key, then the context is assumed to be the insert mode when `save()` is called.

#### Finding Records

```php
// Find by ID
// Clearing is good practice
$itemTable->clear();
$itemTable->id = 1;
$itemTable->find();
$itemTable->next();
echo $itemTable->string_value; // Access field values

// Find all records
$itemTable->clear();
if ($itemTable->find()) {
    while ($itemTable->next()) {
        echo $itemTable->id . ": " . $itemTable->string_value . "\n";
    }
}

// Find with conditions, a list of conditions is at the end of the README
$itemTable->clear();
// gt is "greaterThan" - greaterThan() may also be used; see list of operators at the end of this document
$itemTable->gt('int_value', 3);
if ($itemTable->find()) {
    while ($itemTable->next()) {
        echo $itemTable->int_value . "\n";
    }
}
```

#### Query Operations

```php
// LIKE queries
$itemTable->clear();
$itemTable->like('string_value', '%bunny%');
$itemTable->find();

// IN queries
$itemTable->clear();
$itemTable->in('int_value', [3, 5]);
$itemTable->find();

// NOT LIKE queries
$itemTable->clear();
$itemTable->notLike('string_value', 'bunny rabbit foo-foo');
$itemTable->find();

// Ordering
$itemTable->clear();
$itemTable->orderBy('id', OrderBy::DESC);
$itemTable->gt('id', 1);
$itemTable->find();
```

#### Inserting Records

If the underlying database supports it and is properly configured, then `save()`s will automatically propagate auto-generated primary key values. For non-autosequencing primary keys, you will have to perform a `clear()` then a `find()` using suitable constraints to fetch the record.

```php
$itemTable->clear();
$itemTable->string_value = "New Item";
$itemTable->int_value = 42;
$itemTable->save();
echo "New ID: " . $itemTable->id;
```

#### Updating Records

```php
// Find the record first
$itemTable->clear();
$itemTable->id = 1;
$itemTable->find();
$itemTable->next();

// Update fields
$itemTable->string_value = "Updated Value";
$itemTable->int_value = 77;
$itemTable->save();
```

#### Deleting Records

```php
// Delete by ID
$itemTable->clear();
$itemTable->id = 1;
$itemTable->delete();

// Delete with conditions
$itemTable->clear();
// alternatively $itemTable->startsWith('string_value', 'bunny');
$itemTable->like('string_value', 'bunny%');
$itemTable->delete();

// Delete all records
$itemTable->clear();
$itemTable->delete();
```

#### Pagination and Limits

```php
// Pagination
$itemTable->clear();
$itemTable->page(2, 1); // 2 records per page, page 1
$itemTable->find();

// Limits
$itemTable->clear();
$itemTable->limit(10); // Limit to 10 records
$itemTable->find();

// Limit with offset
$itemTable->clear();
$itemTable->limit(5, 10); // Offset 5, limit 10
$itemTable->find();
```

#### Counting Records

```php
$itemTable->clear();
$count = $itemTable->count();
echo "Total records: " . $itemTable->row_count;
```

### Working with SQL

Aaro will work with record sets using the same general principles. Aaro relies on direct SQL statements rather than a DSL wrapper over SQL. The tradeoff is that the SQL is not portable between RDMSes, however:
1. Right now, Aaro only uses MariaDB/MySQL as a backend
2. DSLs are not terribly portable and result in a lot of their own headaches
3. The likelihood of RDMS swapping in a project is vanishingly low. Implementing a DSL over SQL is edge-casing an RDMS swap that should be given considerably more attention than just switching the RDMS. The chance of your code surviving such a swap intact is very, very low.

#### Basic Query Usage

Direct database queries:

```php
$db->clear();
$db->execute("delete from integ where string_value like 'insert_item_test_%'");
$db->clear();
```

```php
$db->clear();
$db->execute("truncate table integ");
$db->clear();
$db->string_value = "2";
$db->int_value = 3;
$db->execute("insert into integ (string_value, int_value) values (:string_value, :int_value)");
$db->clear();
$records = $db->execute("select * from integ");
$records->next();

echo $records->size());
// value "2"
echo $records->string_value;
```

All fields returned by a query will show up as public properties of the resulting record set:

```php
$db->clear();
$records = $db->execute("select a.one, a.two, b.one as three, c.two as four from a left join b on a.id = b.fk");
echo $records->one . " => " $records.four;
```

Field name collisions are a function of your RDMS and underlying database driver. If there are field collisions (such as `select a.*, b* ...`), Aaro makes no attempt to disambiguate, and the field names and table column associations are left up to the RDMS and database driver selected.

## EntityManager Usage

The EntityManager provides a higher-level abstraction for working with entities and managing object state.

### Setting Up EntityManager

```php
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;

// Configure EntityManager
$entityManager = EntityManager::builder()
    ->database($db)
    ->dataAccessPolicy($tablePolicy)
    ->repositoryPolicy(UncachedPolicy::builder()->build())
    ->build();

// Configure as singleton
EntityManager::configure($entityManager);
```

### Working with Entities

An EntityMapper wraps a given table or record set and provides manual and automatic persistence.

```php
// Create an EntityOf
$entityMapper = EntityMapper::builder()
    ->table($itemTable)
    ->build();

// Find and get entity
$entityMapper->clear();
$entityMapper->id = 1;
$entityMapper->find();
$entityMapper->next();

$entity = $entityMapper->getEntity();
echo $entity->string_value; // Access entity properties
echo $entity->int_value;

// Modify entity
$entity->string_value = "Modified Value";
$entity->int_value = 99;

// $entity id 1 is automatically persisted on shutdown
```

### Working with Custom SQL Queries

```php
// Execute custom SQL
$entityMapper->clear();
$entityMapper->query("SELECT * FROM integ WHERE id = :id");
$entityMapper->id = 1;
$entityMapper->execute();
$entityMapper->next();

$entity = $entityMapper->getEntity();
echo $entity->string_value;
```

### Entity State Management

Entities may be manually persisted using various `persist*()` methods. 

```php
// Get entity by ID from EntityManager
$entity = EntityManager::getManager()->getEntity('table_name', 1);

$entity->string_value = "new value";

// Persist a specific entity
EntityManager::getManager()->persist($entity);

// Persist all entities across all mappers
EntityManager::getManager()->persistAll();

// Persist all entities for a specific mapper
EntityManager::getManager()->persistMapper('table_name');

// Persist with no arguments (same as persistAll)
EntityManager::getManager()->persist();
```

## RepositoryEntity Mode

The RepositoryEntity abstraction level provides a high-level, type-safe interface for working with database entities. This mode uses the Repository pattern with attribute-based configuration.

### Setting Up RepositoryEntity Mode

First, create a Repository class that extends `Repository` and implements `EntityRepositoryInterface`:

```php
use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;

#[RepositoryOf("items", ItemEntity::class)]
class ItemRepository extends Repository implements EntityRepositoryInterface
{
    public static function getTableName()
    {
        return 'items';
    }

    public static function getEntityClass()
    {
        return ItemEntity::class;
    }
}
```

Then, create a RepositoryEntity class that extends `RepositoryEntity`:

```php
use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;

#[EntityOf(ItemRepository::class)]
class ItemEntity extends RepositoryEntity
{
    use Builder, ToBuilder, Data;

    #[PrimaryKey]
    private ?int $id;

    #[Field('string_value')]
    private ?string $name;

    #[Field('int_value')]
    private ?int $quantity;
}
```

### Working with RepositoryEntity

```php
// Get repository from EntityManager
$itemRepository = EntityManager::getManager()->getRepository(ItemRepository::class);

// Fetch an entity by ID
$item = $itemRepository->fetch(1);
echo $item->getName();

// Create a new entity
$newItem = $itemRepository->createEntity();
$newItem->setName("New Item");
$newItem->setQuantity(10);

// Persist the entity
EntityManager::getManager()->persist($newItem);

// Or use the builder pattern
$item = ItemEntity::builder()
    ->name("Another Item")
    ->quantity(5)
    ->build();

EntityManager::getManager()->persist($item);

// Fetch with conditions
$item = $itemRepository->fetchBy('name', 'Specific Item');

// Update entity
$item->setName("Updated Name");
EntityManager::getManager()->persist($item);
```

### RepositoryEntity Features

- **Type Safety**: Strongly typed entities with IDE autocomplete support
- **Field Mapping**: Map database columns to entity properties using `#[Field]` attribute
- **Automatic Mapper Resolution**: Entities automatically resolve their mapper from the `#[EntityOf]` attribute
- **Builder Pattern**: Create entities using a fluent builder interface
- **Change Tracking**: Entities track changes and only persist modified fields
- **Type Conversions**: Automatic conversion between database types and PHP types (e.g., DateTime)

### AuditRepository Feature

The AuditRepository feature provides automatic audit logging for RepositoryEntity classes. When enabled, all insert, update, and delete operations are automatically logged to an audit log table.

#### Setting Up AuditRepository

To enable audit logging for a RepositoryEntity, simply use the `AuditRepositoryEntityTrait`:

```php
use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\ActiveRecordOrm\Feature\Entity\Repository\AuditRepositoryEntityTrait;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;

#[EntityOf(ItemRepository::class)]
class ItemEntity extends RepositoryEntity
{
    use Builder, ToBuilder, Data, AuditRepositoryEntityTrait;

    #[PrimaryKey]
    private ?int $id;

    #[Field('string_value')]
    private ?string $name;

    #[Field('int_value')]
    private ?int $quantity;
}
```

#### Audit Log Table Structure

The audit log table is automatically created with the name `{table_name}_audit_log` and includes the following fields:

- **record_id**: Primary key value from the source table
- **log_datetime**: Timestamp of when the audit entry was created
- **fields**: JSON array of field names that were affected by the operation
- **action**: Enum value (`insert`, `update`, or `delete`) indicating the type of operation
- **by_whom_id**: Integer reference to the actor performing the operation (configurable via `byWhomSupplier`)

#### How It Works

When you use `AuditRepositoryEntityTrait`, the entity automatically:

1. Creates a separate EntityManager that uses `AuditTableFactory` for mapper creation
2. Wraps all table operations in an `AuditTable` that intercepts `save()` and `delete()` operations
3. Logs all changes to the audit log table with metadata about what was changed

#### Example Usage

```php
// Create an entity with audit logging
$item = ItemEntity::builder()
    ->name("New Item")
    ->quantity(10)
    ->build();

// Persist the entity - this automatically creates an audit log entry
EntityManager::getManager()->persist($item);

// The audit log table 'items_audit_log' now contains:
// - record_id: 1 (the new item's ID)
// - action: "insert"
// - fields: ["name", "quantity"]
// - log_datetime: [current timestamp]

// Update the entity
$item->setQuantity(20);
EntityManager::getManager()->persist($item);

// The audit log now has a second entry:
// - record_id: 1
// - action: "update"
// - fields: ["quantity"]
// - log_datetime: [timestamp of update]

// Delete the entity
EntityManager::getManager()->delete($item);

// The audit log now has a third entry:
// - record_id: 1
// - action: "delete"
// - fields: []
// - log_datetime: [timestamp of deletion]
```

#### Accessing Audit Logs

You can query the audit log table directly:

```php
// Access the audit log table
$auditLogTable = TableFactory::build($db, $tablePolicy, 'items_audit_log');

// Find all audit entries for a specific record
$auditLogTable->clear();
$auditLogTable->record_id = 1;
$auditLogTable->find();

while ($auditLogTable->next()) {
    echo "Action: " . $auditLogTable->action . "\n";
    echo "Fields: " . $auditLogTable->fields . "\n";
    echo "Date: " . $auditLogTable->log_datetime . "\n";
}
```

The AuditRepository feature is particularly useful for compliance requirements, debugging, and maintaining a complete history of data changes.

## Advanced Features

### Custom Data Access Policies

You can implement custom data access policies by extending the base policy classes:

```php
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;

class CustomDataAccessPolicy implements DataAccessPolicy
{
    // Implement your custom caching or data access logic
}
```

### Builder Pattern

Most classes in this ORM use the builder pattern for configuration:

```php
$table = Table::builder()
    ->database($db)
    ->tableSchema($schema)
    ->queryBuilder($queryBuilder)
    ->dataAccessPolicy($policy)
    ->fieldSet($fieldSet)
    ->tableName('users')
    ->build();
```

### Comparison Operators
- gt, greater, greaterThan
- gte, greaterThanOrEqualTo
- lt, less, lessThan
- lte, lessThanOrEqualTo
- equals
- set
- like
- notLike
- contains
- startsWith
- endsWith
- in
- notIn
- between
- notBetween
- isNull
- isNotNull
- and
- or

## Testing

The library includes comprehensive unit and integration tests. Run tests with:

```bash
composer test
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Repository Generator Tool

The `repository.php` command-line tool automates the generation of Repository and RepositoryEntity classes, database schemas, and Phinx migrations. This tool helps you quickly scaffold your data access layer from existing MySQL databases or generate migration files from your entity classes.

### Basic Usage

The tool can be run directly (if executable) or via PHP:

```bash
# Direct execution (if executable)
./repository <command> [options]

# Or via PHP
php bin/repository.php <command> [options]
```

### Commands Overview

- **`classes`** - Generate Repository and RepositoryEntity classes by inspecting MySQL database schemas
- **`schema`** - Generate MySQL CREATE TABLE SQL from existing RepositoryEntity class definitions
- **`phinx`** - Generate Phinx migration files from RepositoryEntity class definitions
- **`audit`** - Generate audit-related classes, schemas, or migrations for audit logging

### Classes Command

The `classes` command generates Repository and RepositoryEntity classes by inspecting an existing MySQL table schema.

**Basic Usage:**
```bash
repository.php classes --env=<path> [--table=<table>] --out-dir=<path>
```

**Options:**
- `--env=<path>` - Path to `.env` file or directory containing `.env` file (required)
- `--table=<table>` - Optional: Table name in snake_case (e.g., `user_profiles`). If omitted, generates classes for all tables in the database (excluding tables in `.exclusions`)
- `--out-dir=<path>` - Output directory for generated PHP files (required)

**Examples:**
```bash
# Generate classes for a specific table
repository.php classes --env=./.env --table=user_profiles --out-dir=./src/Entity

# Generate classes for all tables (excluding .exclusions)
repository.php classes --env=./.env --out-dir=./src/Entity
```

**Output:**
- `{Table}Repository.php` - Repository class extending `Repository`
- `{Table}RepositoryEntity.php` - RepositoryEntity class with all table fields mapped as properties

### Schema Command

The `schema` command generates MySQL CREATE TABLE SQL from existing RepositoryEntity class definitions.

**Basic Usage:**
```bash
repository.php schema --source=<path> [--table=<table>]
```

**Options:**
- `--source=<path>` - Directory containing Repository and RepositoryEntity classes (required)
- `--table=<table>` - Optional: Table name in snake_case. If omitted, generates SQL for all RepositoryEntity classes found

**Examples:**
```bash
# Generate SQL for a specific table
repository.php schema --source=./src/Entity --table=user_profiles

# Generate SQL for all RepositoryEntity classes
repository.php schema --source=./src/Entity
```

**Output:**
- `{table}.sql` - MySQL CREATE TABLE statement for each RepositoryEntity class

### Phinx Command

The `phinx` command generates Phinx migration code from existing RepositoryEntity class definitions.

**Basic Usage:**
```bash
repository.php phinx --source=<path> [--table=<table>] --file=<path>
```

**Options:**
- `--source=<path>` - Directory containing Repository and RepositoryEntity classes (required)
- `--table=<table>` - Optional: Table name in snake_case. If omitted, adds CREATE TABLE for all RepositoryEntity classes
- `--file=<path>` - Path to Phinx migration file to generate (file must exist)

**Examples:**
```bash
# Generate Phinx migration for a specific table
repository.php phinx --source=./src/Entity --table=user_profiles --file=./db/migrations/20251215143314_create_user_profiles.php

# Generate Phinx migration for all RepositoryEntity classes
repository.php phinx --source=./src/Entity --file=./db/migrations/20251215143314_create_all_tables.php
```

**Output:**
- Updates the specified Phinx migration file with `create()` method containing table creation code

### Audit Command

The `audit` command provides sub-commands for generating audit-related infrastructure.

#### Audit Classes Sub-command

Generates Repository and RepositoryEntity classes with audit support (includes `AuditRepositoryEntityTrait`).

**Usage:**
```bash
repository.php audit --classes --env=<path> [--table=<table>] --out-dir=<path>
```

**Options:**
- `--env=<path>` - Path to `.env` file or directory containing `.env` file (required)
- `--out-dir=<path>` - Output directory for generated PHP files (required)
- `--table=<table>` - Optional: Table name. If omitted, generates for all tables not in `.exclusions`

**Examples:**
```bash
# Generate audit classes for a specific table
repository.php audit --classes --env=./.env --table=user_profiles --out-dir=./src/Entity

# Generate audit classes for all tables
repository.php audit --classes --env=./.env --out-dir=./src/Entity
```

#### Audit Schema Sub-command

Generates MySQL CREATE TABLE SQL for audit log tables.

**Usage:**
```bash
# From RepositoryEntity classes
repository.php audit --schema --source=<path> [--table=<table>]

# From MySQL database
repository.php audit --schema --env=<path> [--table=<table>] --out-dir=<path>
```

**Options:**
- `--source=<path>` - Directory containing RepositoryEntity classes (for class-based generation)
- `--env=<path>` - Path to `.env` file (for database-based generation)
- `--out-dir=<path>` - Output directory (required when using `--env`)
- `--table=<table>` - Optional: Table name. If omitted, processes all tables/classes

**Output:**
- `YmdHis.sql` - Timestamped SQL file when `--table` is omitted
- `audit_log_tables.sql` - SQL file when `--table` is specified

#### Audit Phinx Sub-command

Generates Phinx migration files for audit log tables.

**Usage:**
```bash
# From RepositoryEntity classes
repository.php audit --phinx --source=<path> [--table=<table>] --file=<path>

# From MySQL database
repository.php audit --phinx --env=<path> [--table=<table>] --out-dir=<path>
```

**Options:**
- `--source=<path>` - Directory containing RepositoryEntity classes (for class-based generation)
- `--file=<path>` - Path to Phinx migration file (required when using `--source` with `--table`)
- `--env=<path>` - Path to `.env` file (for database-based generation)
- `--out-dir=<path>` - Output directory (required when using `--env`)
- `--table=<table>` - Optional: Table name. If omitted, generates `YmdHis_audit_log.php` with all tables

**Output:**
- `YmdHis_audit_log.php` - Timestamped migration file when `--table` is omitted
- Individual migration files when `--table` is specified

#### Audit Migrate Sub-command

Runs both `--classes` and `--phinx` commands sequentially to generate complete audit infrastructure.

**Usage:**
```bash
repository.php audit --migrate --env=<path> [--table=<table>] --out-dir=<path>
```

**Options:**
- `--env=<path>` - Path to `.env` file or directory containing `.env` file (required)
- `--out-dir=<path>` - Output directory for generated files (required)
- `--table=<table>` - Optional: Table name. If omitted, processes all tables not in `.exclusions`

**Examples:**
```bash
# Generate audit classes and Phinx migration for a specific table
repository.php audit --migrate --env=./.env --out-dir=./src/Entity --table=user_profiles

# Generate audit classes and Phinx migration for all tables
repository.php audit --migrate --env=./.env --out-dir=./src/Entity
```

**What it does:**
1. Generates Repository and RepositoryEntity classes with `AuditRepositoryEntityTrait`
2. Generates Phinx migration files for audit log tables

### Exclusions File

The tool supports excluding tables from batch operations using a `.exclusions` file located in the `bin/` directory. This file supports:

- **Exact matches**: Table names listed exactly (e.g., `phinxlog`)
- **Glob patterns**: Patterns using `*` and `?` wildcards (e.g., `*_audit_log`)

**Example `.exclusions` file:**
```
phinxlog
*_audit_log
```

Tables matching entries in `.exclusions` are automatically excluded when:
- Running `classes` command without `--table`
- Running `audit --classes` without `--table`
- Running `audit --schema` without `--table` (when using `--env`)

### Environment File Handling

For commands that require `--env`, you can specify either:
- A full path to a `.env` file: `--env=./path/to/.env`
- A directory containing a `.env` file: `--env=./path/to/directory` (the tool automatically appends `.env`)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For support and questions, please open an issue on the [GitHub repository](https://github.com/amtgard/active-record-orm).
