# Snapshots and Diffs

This guide covers bootstrapping migrations from an existing database and
generating migration diffs from schema changes.

## Generating Migration Snapshots from an Existing Database

If you have a pre-existing database and want to start using migrations, or want
to version-control the initial schema of your application, run
`bake migration_snapshot`:

```bash
bin/cake bake migration_snapshot Initial
```

It will generate a migration file called `YYYYMMDDHHMMSS_Initial.php`
containing all the create statements for all tables in your database.

By default, the snapshot will be created by connecting to the database defined
in the `default` connection configuration. If you need to bake a snapshot from
a different datasource, use the `--connection` option:

```bash
bin/cake bake migration_snapshot Initial --connection my_other_connection
```

You can also make sure the snapshot includes only the tables for which you have
defined the corresponding model classes by using the `--require-table` flag:

```bash
bin/cake bake migration_snapshot Initial --require-table
```

When using `--require-table`, the shell will look through your application's
`Table` classes and will only add the model tables to the snapshot.

If you want to generate a snapshot without marking it as migrated, for example
for use in unit tests, use the `--generate-only` flag:

```bash
bin/cake bake migration_snapshot Initial --generate-only
```

This will create the migration file but will not add an entry to the migrations
tracking table, allowing you to move the file to a different location without
causing `MISSING` status issues.

To bake a snapshot for a plugin, use the `--plugin` option:

```bash
bin/cake bake migration_snapshot Initial --plugin MyPlugin
```

Only the tables that have a `Table` object model class defined will be added to
the snapshot of your plugin.

> [!NOTE]
> When baking a snapshot for a plugin, the migration files will be created in
> your plugin's `config/Migrations` directory.

Be aware that when you bake a snapshot, it is automatically added to the
migrations log table as migrated unless you use `--generate-only`.

## Generating a Diff

As migrations are applied and rolled back, the migrations plugin will generate
a dump file of your schema. If you make manual changes to your database schema
outside of migrations, you can use `bake migration_diff` to generate a
migration file that captures the difference between the current schema dump file
and the database schema:

```bash
bin/cake bake migration_diff NameOfTheMigrations
```

By default, the diff will be created by connecting to the database defined in
the `default` connection configuration. If you need to bake a diff from a
different datasource, use the `--connection` option:

```bash
bin/cake bake migration_diff NameOfTheMigrations --connection my_other_connection
```

If you want to use the diff feature on an application that already has a
migrations history, you need to manually create the dump file that will be used
as comparison:

```bash
bin/cake migrations dump
```

The database state must be the same as it would be if you had just migrated all
your migrations before you create a dump file. Once the dump file is generated,
you can start doing changes in your database and use
`bake migration_diff` whenever you need to capture them.

> [!NOTE]
> Migration diff generation cannot detect column renamings.

## Generating a Dump File

The dump command creates a file to be used with `bake migration_diff`:

```bash
bin/cake migrations dump
```

Each generated dump file is specific to the connection it is generated from,
and is suffixed as such. This allows `bake migration_diff` to properly compute
diffs when your application is dealing with multiple databases, possibly from
different vendors.

Dump files are created in the same directory as your migration files.

You can also use the `--source`, `--connection`, and `--plugin` options just
like for the `migrate` command.
