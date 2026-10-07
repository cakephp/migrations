# Migrations 5.x

Migrations is a plugin that lets you track changes to your database schema over
time as PHP code that accompanies your application. This lets you ensure each
environment your application runs in has the appropriate schema by applying
migrations.

Instead of writing schema modifications in SQL, this plugin allows you to
define schema changes with a high-level database portable API.

## What's New in 5.x

Migrations 5.x includes several new features:

- **Seed tracking** - Seeds are now tracked in a `cake_seeds` table, preventing
  accidental re-runs
- **Check constraints** - Support for database check constraints via
  `addCheckConstraint()`
- **Default values in bake** - Specify default values when baking migrations
  (e.g., `active:boolean:default[true]`)
- **MySQL ALTER options** - Control `ALGORITHM` and `LOCK` for ALTER TABLE
  operations
- **insertOrSkip()** - New method for idempotent seed data insertion

See the [Upgrading from 4.x to 5.x](upgrades/upgrading-from-4-x) guide for breaking changes and
migration steps from 4.x.

## Installation

By default Migrations is installed with the application skeleton. If you've
removed it and want to re-install it, run the following from your application's
root directory:

```bash
php composer.phar require cakephp/migrations "@stable"

# Or if composer is installed globally
composer require cakephp/migrations "@stable"
```

To use the plugin, load it in your application's `config/bootstrap.php` file:

```bash
bin/cake plugin load Migrations
```

Or load the plugin in `src/Application.php`:

```php
$this->addPlugin('Migrations');
```

Additionally, configure the default database connection in `config/app.php` as
explained in the [Database Configuration section](https://book.cakephp.org/5/en/orm/database-basics.html#database-configuration).

## Overview

A migration is a PHP file that describes the changes to apply to your database.
A migration file can add, change, or remove tables, columns, indexes, and
foreign keys.

If we wanted to create a table, we could use a migration similar to this:

```php
<?php
use Migrations\BaseMigration;

class CreateProducts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('products');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->create();
    }
}
```

When applied, this migration will add a table to your database named
`products` with the following column definitions:

- `id` column of type `integer` as primary key. This column is added
  implicitly, but you can customize the name and type if necessary.
- `name` column of type `string`
- `description` column of type `text`
- `created` column of type `datetime`
- `modified` column of type `datetime`

> [!NOTE]
> Migrations are not automatically applied. Use the CLI commands to apply and
> roll back migrations.

```bash
bin/cake migrations migrate
```

## Guide Map

Use the focused guides below instead of a single long reference page:

- [Installation and Overview](getting-started/installation-and-overview)
- [Creating Migrations](getting-started/creating-migrations)
- [Snapshots and Diffs](getting-started/snapshots-and-diffs)
- [Running and Managing Migrations](getting-started/running-and-managing-migrations)
- [Integration and Deployment](advanced/integration-and-deployment)
- [Writing Migrations](guides/writing-migrations)
- [Using the Query Builder](guides/using-the-query-builder)
- [Executing Queries](guides/executing-queries)
- [Database Seeding](guides/seeding)
- [Upgrading to the builtin backend](upgrades/upgrading-to-builtin-backend)

## Upgrading from 4.x

If you are upgrading from Migrations 4.x, see the
[Upgrading from 4.x to 5.x](upgrades/upgrading-from-4-x) guide.

## Creating Migrations

Migration naming conventions, bake patterns, column syntax, and generated
examples are covered in [Creating Migrations](getting-started/creating-migrations).

## Generating migration snapshots from an existing database

For snapshot generation, diff workflows, and dump files, see
[Snapshots and Diffs](getting-started/snapshots-and-diffs).

## Generating a diff

The `bake migration_diff` workflow is also covered in
[Snapshots and Diffs](getting-started/snapshots-and-diffs).

## Applying Migrations

For `migrate`, `rollback`, `status`, `mark_migrated`, and related command
options, see
[Running and Managing Migrations](getting-started/running-and-managing-migrations).

## Reverting Migrations

Rollback usage is documented in
[Running and Managing Migrations](getting-started/running-and-managing-migrations).

## View Migrations Status

Status commands, cleanup, and JSON output are documented in
[Running and Managing Migrations](getting-started/running-and-managing-migrations).

## Marking a migration as migrated

See [Running and Managing Migrations](getting-started/running-and-managing-migrations)
for `mark_migrated` examples and caveats.

## Seeding your database

Seed classes are documented in [Database Seeding](guides/seeding).

## Generating a dump file

Dump generation is documented in
[Snapshots and Diffs](getting-started/snapshots-and-diffs).

## Using Migrations for Tests

Test bootstrapping with `Migrations\TestSuite\Migrator` is covered in
[Integration and Deployment](advanced/integration-and-deployment).

## Using Migrations In Plugins

Plugin-scoped migration workflows are covered in
[Integration and Deployment](advanced/integration-and-deployment).

## Running Migrations in a non-shell environment

Programmatic execution through `Migrations\Migrations` is covered in
[Integration and Deployment](advanced/integration-and-deployment).

## Feature Flags

Compatibility feature flags are covered in
[Integration and Deployment](advanced/integration-and-deployment).

## Skipping the `schema.lock` file generation

The `--no-lock` workflow is covered in
[Integration and Deployment](advanced/integration-and-deployment).

## Deployment

Deployment guidance and schema cache refresh steps are covered in
[Integration and Deployment](advanced/integration-and-deployment).

## Alert of missing migrations

Local development alerts for pending migrations are covered in
[Integration and Deployment](advanced/integration-and-deployment).

## IDE autocomplete support

IDE-related tooling notes are covered in
[Integration and Deployment](advanced/integration-and-deployment).
