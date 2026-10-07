# Schema Introspection and Platform Limitations

## Checking Columns

`BaseMigration` also provides methods for introspecting the current schema,
allowing you to conditionally make changes to schema, or read data. Schema is
inspected when the migration is run.

### Get a Column List

To retrieve all table columns, create a table object and call `getColumns()`:

```php
<?php

use Migrations\BaseMigration;

class ColumnListMigration extends BaseMigration
{
    public function up(): void
    {
        $columns = $this->table('users')->getColumns();
    }
}
```

### Get a Column by Name

To retrieve one table column, call `getColumn()`:

```php
<?php

use Migrations\BaseMigration;

class ColumnListMigration extends BaseMigration
{
    public function up(): void
    {
        $column = $this->table('users')->getColumn('email');
    }
}
```

### Check Whether a Column Exists

Use `hasColumn()` to determine whether a table already has a given column:

```php
<?php

use Migrations\BaseMigration;

class MyNewMigration extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('user');
        if ($table->hasColumn('username')) {
            // do something
        }
    }
}
```

## Changing Templates

See [Custom Seed Migration Templates](../seeding#custom-seed-migration-templates)
for how to customize the templates used to generate migrations.

## Database-Specific Limitations

While Migrations aims to provide a database-agnostic API, some features have
database-specific limitations or are not available on all platforms.

### SQL Server

The following features are not supported on SQL Server:

- Check constraints are not currently implemented
- Table comments are not supported
- `insertOrSkip()` is not supported; use `insertOrUpdate()` instead

### SQLite

SQLite limitations include:

- named foreign keys are not supported
- table comments are stored as metadata, not in the database itself
- altering check constraints requires recreating the table
- table partitioning is not supported

### PostgreSQL

PostgreSQL does not support MySQL's `KEY` partitioning type. Use `HASH`
partitioning instead for similar distribution behavior.

### MySQL/MariaDB

For MySQL, the `$conflictColumns` parameter in `insertOrUpdate()` is ignored
because MySQL's `ON DUPLICATE KEY UPDATE` automatically applies to all unique
constraints. PostgreSQL and SQLite require this parameter to be specified.

Some geometry column features may not work correctly on MariaDB due to
differences in GIS implementation compared to MySQL.
