# Amtgard Active Record ORM

A modern Active Record ORM for PHP 8.3+ designed for the ORK4 system. This library provides a clean, intuitive interface for database operations with support for both traditional table-based queries and entity-based object mapping.

## Introduction

Amtgard Active Record ORM (Aaro) is an active record data access layer, in the vein of PorqDB - a completely dead ORM project from the dawn of time.

Aaro focuses on two basic use cases: CRUD operations with basic constraints and SQL record sets. Aaro does not offer facilities for modeling relationships or a DSL over SQL - the concept is that SQL is already the most robust language for this purpose.

In addition to basic table- and record set-level access, Aaro offers a basic Entity Manager system for automatically persisting records to the database when the current script terminates or explicitly when `flush()`ed.

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
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\TableFactory;
use Dotenv\Dotenv;

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
// Create an EntityMapper
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

Entities may be manually persisted by calling `flushAll()` or `flushMapper('item_table')`. 

```php
// Get entity by ID from EntityManager
$entity = EntityManager::getManager()->getEntity('table_name', 1);

$entity->string_value = "new value";

// Flush all changes
EntityManager::getManager()->flushAll();
```

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

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For support and questions, please open an issue on the [GitHub repository](https://github.com/amtgard/active-record-orm).
