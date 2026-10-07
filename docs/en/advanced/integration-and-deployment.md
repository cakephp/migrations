# Integration and Deployment

This guide covers test integration, plugin usage, programmatic execution, and
deployment-related operational concerns.

## Using Migrations for Tests

If you are using migrations for your application schema, you can also use those
same migrations to build schema in your tests. In your application's
`tests/bootstrap.php` file you can use the `Migrator` class to build schema
when tests are run. The `Migrator` will use existing schema if it is current,
and if the migration history in the database differs from what is in the
filesystem, all tables will be dropped and migrations will be rerun from the
beginning:

```php
// in tests/bootstrap.php
use Migrations\TestSuite\Migrator;

$migrator = new Migrator();

// Simple setup with no plugins
$migrator->run();

// Run a non-test database
$migrator->run(['connection' => 'test_other']);

// Run migrations for plugins
$migrator->run(['plugin' => 'Contacts']);

// Run the Documents migrations on the test_docs connection
$migrator->run(['plugin' => 'Documents', 'connection' => 'test_docs']);
```

If you need to run multiple sets of migrations, use `runMany()`:

```php
$migrator->runMany([
    ['plugin' => 'Contacts'],
    ['plugin' => 'Documents', 'connection' => 'test_docs'],
]);
```

If your database also contains tables that are not managed by your application,
such as those created by PostGIS, you can exclude those tables from drop and
truncate behavior using the `skip` option:

```php
$migrator->run(['connection' => 'test', 'skip' => ['postgis*']]);
```

The `skip` option accepts an `fnmatch()` compatible pattern.

## Using Migrations in Plugins

Plugins can also provide migration files. All commands in the Migrations plugin
support the `--plugin` or `-p` option to scope execution to the migrations
relative to that plugin:

```bash
bin/cake migrations status -p PluginName
bin/cake migrations migrate -p PluginName
```

To check the app and every loaded plugin in a single call — a typical need on
deploy gates — use `migrations status --all` instead of looping over plugins
manually:

```bash
bin/cake migrations status --all
```

The command prints a compact per-section summary and exits non-zero when
anything is pending, so it slots straight into a CI step. See
[Checking All Plugins at Once](../getting-started/running-and-managing-migrations#checking-all-plugins-at-once)
for the full description of the output and exit codes.

## Running Migrations in a Non-shell Environment

While typical usage of migrations is from the command line, you can also run
migrations from a non-shell environment by using the `Migrations\Migrations`
class. This can be handy when you are developing a plugin installer for a CMS,
for instance.

The `Migrations` class allows you to run the following commands:

- `migrate`
- `rollback`
- `markMigrated`
- `status`
- `seed`

Each of these commands has a corresponding method defined in the
`Migrations\Migrations` class.

```php
use Migrations\Migrations;

$migrations = new Migrations();

$status = $migrations->status();
$migrate = $migrations->migrate();
$rollback = $migrations->rollback();
$markMigrated = $migrations->markMigrated(20150804222900);
$seeded = $migrations->seed();
```

The methods can accept an array of parameters that match the shell command
options:

```php
use Migrations\Migrations;

$migrations = new Migrations();
$status = $migrations->status([
    'connection' => 'custom',
    'source' => 'MyMigrationsFolder',
]);
```

The only exception is `markMigrated()`, which expects the version number as the
first argument and the options array as the second.

Optionally, you can pass default parameters in the constructor:

```php
use Migrations\Migrations;

$migrations = new Migrations([
    'connection' => 'custom',
    'source' => 'MyMigrationsFolder',
]);

$status = $migrations->status();
$migrate = $migrations->migrate();
```

If you need to override one or more default parameters for one call, pass them
to the method:

```php
use Migrations\Migrations;

$migrations = new Migrations([
    'connection' => 'custom',
    'source' => 'MyMigrationsFolder',
]);

$status = $migrations->status();
$migrate = $migrations->migrate(['connection' => 'default']);
```

## Feature Flags

Migrations offers a few feature flags for compatibility. These features are
disabled by default but can be enabled if required:

- `unsigned_primary_keys`: Should Migrations create primary keys as unsigned
  integers? Default: `false`
- `unsigned_ints`: Should Migrations create all integer columns as unsigned?
  Default: `false`
- `column_null_default`: Should Migrations create columns as nullable by
  default? Default: `false`
- `add_timestamps_use_datetime`: Should Migrations use `DATETIME` type columns
  for the columns added by `addTimestamps()`?

Set them via `Configure`, for example in `config/app.php`:

```text
'Migrations' => [
    'unsigned_primary_keys' => true,
    'unsigned_ints' => true,
    'column_null_default' => true,
],
```

> [!NOTE]
> The `unsigned_primary_keys` and `unsigned_ints` options only affect MySQL
> databases. When generating migrations with `bake migration_snapshot` or
> `bake migration_diff`, the `signed` attribute will only be included in the
> output for unsigned columns as `'signed' => false`.

## Skipping the `schema.lock` File Generation

In order for the diff feature to work, a `.lock` file is generated every time
you migrate, roll back, or bake a snapshot, to keep track of the state of your
database schema at any given point in time. You can skip this file generation,
for instance when deploying to production, by using the `--no-lock` option:

```bash
bin/cake migrations migrate --no-lock
bin/cake migrations rollback --no-lock
bin/cake bake migration_snapshot MyMigration --no-lock
```

## Deployment

You should update your deployment scripts to run migrations when new code is
deployed. Ideally, run migrations after the code is on your servers, but before
the application code becomes active.

After running migrations, remember to clear the ORM cache so it renews the
column metadata of your tables. Otherwise, you might end up with errors about
columns not existing when performing operations on those new columns. The
CakePHP core includes a [Schema Cache Shell](https://book.cakephp.org/5/console-commands/schema-cache.html) that you can use:

```bash
bin/cake migrations migrate
bin/cake schema_cache clear
```

## Alert of Missing Migrations

You can use the `Migrations.PendingMigrations` middleware in local development
to alert developers about new migrations that have not been applied:

```php
use Migrations\Middleware\PendingMigrationsMiddleware;

$config = [
    'plugins' => [
        // Optionally include a list of plugins with migrations to check.
    ],
];

$middlewareQueue
    // ErrorHandler middleware
    ->add(new PendingMigrationsMiddleware($config));
```

You can add the `'app'` config key set to `false` if you are only interested in
checking plugin migrations.

You can temporarily disable the migration check by adding
`skip-migration-check=1` to the URL query string.

## IDE Autocomplete Support

The [IdeHelper plugin](https://github.com/dereuromark/cakephp-ide-helper) can
help you get more IDE support for tables, their column names, and possible
column types. Specifically, PHPStorm understands the meta information and can
help you autocomplete those.
