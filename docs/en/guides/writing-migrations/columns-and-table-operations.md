# Columns and Table Operations

<a id="adding-columns"></a>

## Adding Columns

Column types are specified as strings and can be one of:

- binary
- boolean
- char
- date
- datetime
- decimal
- float
- double
- smallinteger
- integer
- biginteger
- string
- text
- time
- timestamp
- uuid
- binaryuuid
- nativeuuid

In addition, the MySQL adapter supports `enum`, `set`, `blob`, `tinyblob`,
`mediumblob`, `longblob`, `bit` and `json` column types (`json` in MySQL 5.7
and above). When providing a limit value and using `binary`, `varbinary` or
`blob` and its subtypes, the retained column type will be based on required
length (see [Limit Option and MySQL](#limit-option-and-mysql) for details).

With most adapters, the `uuid` and `nativeuuid` column types are aliases,
however with the MySQL adapter + MariaDB, the `nativeuuid` type maps to a
native UUID column instead of `CHAR(36)` like `uuid` does.

In addition, the Postgres adapter supports `interval`, `json`, `jsonb`, `uuid`,
`cidr`, `inet` and `macaddr` column types (PostgreSQL 9.3 and above).

### Valid Column Options

The following are valid column options.

For any column type:

| Option  | Description                                                                |
|---------|----------------------------------------------------------------------------|
| limit   | set maximum length for strings, also hints column types in adapters        |
| length  | alias for `limit`                                                          |
| default | set default value or action                                                |
| null    | allow `NULL` values, defaults to `true`                                    |
| after   | specify the column that a new column should be placed after *(MySQL only)* |
| comment | set a text comment on the column                                           |

For `decimal` and `float` columns:

| Option    | Description                                            |
|-----------|--------------------------------------------------------|
| precision | total number of digits                                 |
| scale     | number of digits after the decimal point               |
| signed    | enable or disable the `unsigned` option *(MySQL only)* |

> [!NOTE]
> Migrations follows the SQL standard where `precision` is the total number of
> digits and `scale` is digits after the decimal point.

For `enum` and `set` columns:

| Option | Description                                |
|--------|--------------------------------------------|
| values | comma separated list or an array of values |

For `smallinteger`, `integer` and `biginteger` columns:

| Option   | Description                                            |
|----------|--------------------------------------------------------|
| identity | enable or disable automatic incrementing               |
| signed   | enable or disable the `unsigned` option *(MySQL only)* |

For `date`, `time`, `datetime`, and `timestamp` columns, default/timezone/update
options are supported as appropriate for the adapter. For `string` and `text`,
MySQL also supports `collation` and `encoding`.

You can add `created` and `updated` timestamps using `addTimestamps()` or
`addTimestampsWithTimezone()`:

```php
$this->table('users')->addTimestamps()->create();
$this->table('users')->addTimestampsWithTimezone()->create();
```

### Limit Option and MySQL

When using the MySQL adapter, there are a couple things to consider when
working with limits:

- When using a `string` primary key or index on MySQL 5.7 or below, or the
  MyISAM storage engine, and the default charset of `utf8mb4_unicode_ci`, you
  must specify a limit less than or equal to 191, or use a different charset.
- Additional hinting of database column type can be made for `integer`, `text`,
  `blob`, `tinyblob`, `mediumblob`, `longblob` columns.

```php
<?php

use Migrations\Db\Adapter\MysqlAdapter;

$table = $this->table('cart_items');
$table->addColumn('user_id', 'integer')
      ->addColumn('product_id', 'integer', ['limit' => MysqlAdapter::INT_BIG])
      ->addColumn('subtype_id', 'integer', ['limit' => MysqlAdapter::INT_SMALL])
      ->addColumn('quantity', 'integer', ['limit' => MysqlAdapter::INT_TINY])
      ->create();
```

### Default Values with Expressions

If you need to set a default to an expression, you can use a `Literal` to have
the column's default value used without quoting or escaping:

```php
use Migrations\BaseMigration;
use Migrations\Db\Literal;

class AddSomeColumns extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
              ->addColumn('uniqid', 'uuid', [
                  'default' => Literal::from('uuid_generate_v4()'),
              ])
              ->create();
    }
}
```

## Creating a Table

Creating a table is straightforward using the Table object:

```php
<?php

use Migrations\BaseMigration;

class MyNewMigration extends BaseMigration
{
    public function change(): void
    {
        $users = $this->table('users');
        $users->addColumn('username', 'string', ['limit' => 20])
              ->addColumn('password', 'string', ['limit' => 40])
              ->addColumn('email', 'string', ['limit' => 100])
              ->addColumn('created', 'datetime')
              ->addColumn('updated', 'datetime', ['null' => true])
              ->addIndex(['username', 'email'], ['unique' => true])
              ->create();
    }
}
```

Migrations automatically creates an auto-incrementing primary key column called
`id` for every table unless configured otherwise.

You can disable the automatic `id` column and define a custom primary key:

```php
$table = $this->table('followers', [
    'id' => false,
    'primary_key' => ['user_id', 'follower_id'],
]);
```

The MySQL adapter also supports table options such as `comment`, `collation`,
`row_format`, `engine`, `signed`, and `limit`.

To set unsigned primary keys, pass the `signed` option with `false`, or enable
the `Migrations.unsigned_primary_keys` and `Migrations.unsigned_ints` feature
flags together. See [Feature Flags](../../advanced/integration-and-deployment#feature-flags).

## MySQL ALTER TABLE Options

When modifying tables in MySQL, you can control how the `ALTER TABLE`
operation is performed using the `algorithm` and `lock` options:

```php
$table->update([
    'algorithm' => 'INPLACE',
    'lock' => 'NONE',
]);
```

Not all operations support all `algorithm` and `lock` combinations, and MySQL
will raise an error if the requested combination is not possible.

## Table Partitioning

Migrations supports table partitioning for MySQL and PostgreSQL. Partitioning
helps manage large tables by splitting them into smaller, more manageable
pieces.

> [!NOTE]
> Partition columns must be included in the primary key for MySQL. SQLite does
> not support partitioning. MySQL's `RANGE` and `LIST` types only work with
> integer columns - use `RANGE COLUMNS` and `LIST COLUMNS` for DATE/STRING
> columns.

### RANGE Partitioning

RANGE partitioning is useful when you want to partition by numeric ranges. For
MySQL, use `TYPE_RANGE` with integer columns or expressions, and
`TYPE_RANGE_COLUMNS` for DATE/DATETIME/STRING columns:

```php
<?php
use Migrations\BaseMigration;
use Migrations\Db\Table\Partition;

class CreatePartitionedOrders extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('orders', [
            'id' => false,
            'primary_key' => ['id', 'order_date'],
        ]);
        $table->addColumn('id', 'integer', ['identity' => true])
              ->addColumn('order_date', 'date')
              ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
              ->partitionBy(Partition::TYPE_RANGE_COLUMNS, 'order_date')
              ->addPartition('p2022', '2023-01-01')
              ->addPartition('p2023', '2024-01-01')
              ->addPartition('p2024', '2025-01-01')
              ->addPartition('pmax', 'MAXVALUE')
              ->create();
    }
}
```

### LIST Partitioning

LIST partitioning is useful when you want to partition by discrete values. For
MySQL, use `TYPE_LIST` with integer columns and `TYPE_LIST_COLUMNS` for STRING
columns:

```php
<?php
use Migrations\BaseMigration;
use Migrations\Db\Table\Partition;

class CreatePartitionedCustomers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('customers', [
            'id' => false,
            'primary_key' => ['id', 'region'],
        ]);
        $table->addColumn('id', 'integer', ['identity' => true])
              ->addColumn('region', 'string', ['limit' => 20])
              ->addColumn('name', 'string')
              ->partitionBy(Partition::TYPE_LIST_COLUMNS, 'region')
              ->addPartition('p_americas', ['US', 'CA', 'MX', 'BR'])
              ->addPartition('p_europe', ['UK', 'DE', 'FR', 'IT'])
              ->addPartition('p_asia', ['JP', 'CN', 'IN', 'KR'])
              ->create();
    }
}
```

### HASH Partitioning

HASH partitioning distributes data evenly across a specified number of
partitions:

```php
<?php
use Migrations\BaseMigration;
use Migrations\Db\Table\Partition;

class CreatePartitionedSessions extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('sessions');
        $table->addColumn('user_id', 'integer')
              ->addColumn('data', 'text')
              ->partitionBy(Partition::TYPE_HASH, 'user_id', ['count' => 8])
              ->create();
    }
}
```

### KEY Partitioning (MySQL only)

KEY partitioning is similar to HASH but uses MySQL's internal hashing function:

```php
<?php
use Migrations\BaseMigration;
use Migrations\Db\Table\Partition;

class CreatePartitionedCache extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('cache', [
            'id' => false,
            'primary_key' => ['cache_key'],
        ]);
        $table->addColumn('cache_key', 'string', ['limit' => 255])
              ->addColumn('value', 'binary')
              ->partitionBy(Partition::TYPE_KEY, 'cache_key', ['count' => 16])
              ->create();
    }
}
```

### Partitioning with Expressions

You can partition by expressions using the `Literal` class:

```php
<?php
use Migrations\BaseMigration;
use Migrations\Db\Literal;
use Migrations\Db\Table\Partition;

class CreatePartitionedEvents extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('events', [
            'id' => false,
            'primary_key' => ['id', 'created_at'],
        ]);
        $table->addColumn('id', 'integer', ['identity' => true])
              ->addColumn('created_at', 'datetime')
              ->partitionBy(Partition::TYPE_RANGE, Literal::from('YEAR(created_at)'))
              ->addPartition('p2022', 2023)
              ->addPartition('p2023', 2024)
              ->addPartition('pmax', 'MAXVALUE')
              ->create();
    }
}
```

### Modifying Partitions on Existing Tables

You can add or drop partitions on existing partitioned tables:

```php
<?php
use Migrations\BaseMigration;

class ModifyOrdersPartitions extends BaseMigration
{
    public function up(): void
    {
        $this->table('orders')
            ->addPartitionToExisting('p2025', '2026-01-01')
            ->update();
    }

    public function down(): void
    {
        $this->table('orders')
            ->dropPartition('p2025')
            ->update();
    }
}
```

## Saving Changes

When working with the Table object, Migrations stores certain operations in a
pending changes cache. Once you have made the changes you want to the table,
you must save them. Migrations provides three methods:

- `create()` creates the table and runs pending changes
- `update()` runs pending changes on an existing table
- `save()` creates or updates depending on whether the table exists

When using `change()`, you should always use `create()` or `update()`, and
never `save()`.

## Renaming a Column

To rename a column, call `renameColumn()`:

```php
$this->table('users')
    ->renameColumn('bio', 'biography')
    ->save();
```

## Adding a Column After Another Column

When adding a column with the MySQL adapter, you can dictate its position using
the `after` option:

```php
$this->table('users')
    ->addColumn('city', 'string', ['after' => 'email'])
    ->update();
```

The `\Migrations\Db\Adapter\MysqlAdapter::FIRST` constant can be used to place
the new column first.

## Dropping a Column

To drop a column, use `removeColumn()`:

```php
$this->table('users')
    ->removeColumn('short_name')
    ->save();
```

## Specifying a Column Limit

You can limit the maximum length of a column using the `limit` option:

```php
$this->table('tags')
    ->addColumn('short_name', 'string', ['limit' => 30])
    ->update();
```

## Changing Column Attributes

There are two methods for modifying existing columns:

### Updating Columns

To modify specific column attributes while preserving others, use
`updateColumn()`:

```php
$this->table('users')
    ->updateColumn('email', null, ['null' => true])
    ->save();
```

This automatically preserves unspecified attributes such as defaults,
nullability, limits, comments, signedness, collation, and enum/set values.

### Changing Columns

To completely replace a column definition, use `changeColumn()` and specify all
desired attributes:

```php
$this->table('users')
    ->changeColumn('email', 'string', [
        'limit' => 255,
        'null' => true,
        'default' => null,
    ])
    ->save();
```

You can enable attribute preservation with `'preserveUnspecified' => true`.

## Determining Whether a Table Exists

Use `hasTable()` to check whether a table exists:

```php
if ($this->hasTable('users')) {
    // do something
}
```

## Dropping a Table

Tables can be dropped using `drop()`:

```php
$this->table('users')->drop()->save();
```

## Renaming a Table

To rename a table, call `rename()`:

```php
$this->table('users')
    ->rename('legacy_users')
    ->update();
```

## Changing the Primary Key

To change the primary key on an existing table, use `changePrimaryKey()`:

```php
$this->table('users')
    ->changePrimaryKey(['new_id', 'username'])
    ->save();
```

## Creating Custom Primary Keys

You can specify an `autoId` property in the migration class and set it to
`false`, which turns off automatic `id` column creation:

```php
<?php
use Migrations\BaseMigration;

class CreateProductsTable extends BaseMigration
{
    public bool $autoId = false;

    public function up(): void
    {
        $this->table('products')
            ->addColumn('id', 'uuid')
            ->addPrimaryKey('id')
            ->addColumn('name', 'string')
            ->addColumn('description', 'text')
            ->create();
    }
}
```

> [!WARNING]
> Dealing with primary keys can only be done on table creation operations for
> some database servers.

## Changing the Table Comment

To change the comment on an existing table, use `changeComment()`:

```php
$this->table('users')
    ->changeComment('This is the table with users auth information')
    ->save();
```

## Next Steps

- [Indexes and Constraints](indexes-and-constraints)
- [Schema Introspection and Platform Limitations](schema-introspection-and-platform-limitations)
