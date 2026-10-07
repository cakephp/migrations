# Running and Managing Migrations

This guide covers the commands you use after writing or generating migration
files.

## Applying Migrations

Once you have generated or written your migration file, apply the changes to
your database:

```bash
# Run all the migrations
bin/cake migrations migrate

# Migrate to a specific version using the --target option
bin/cake migrations migrate -t 20150103081132

# Run migrations from a custom source directory
bin/cake migrations migrate -s Alternate

# Run migrations against a different connection
bin/cake migrations migrate -c my_custom_connection

# Run migrations for a plugin
bin/cake migrations migrate -p MyAwesomePlugin
```

## Reverting Migrations

The `rollback` command undoes previously executed migrations:

```bash
# Roll back to the previous migration
bin/cake migrations rollback

# Roll back to a specific version
bin/cake migrations rollback -t 20150103081132
```

You can also use the `--source`, `--connection`, and `--plugin` options just
like for the `migrate` command.

## Viewing Migration Status

The `status` command prints a list of all migrations, along with their current
status:

```bash
bin/cake migrations status
```

You can also output the results as JSON:

```bash
bin/cake migrations status --format json
```

You can also use the `--source`, `--connection`, and `--plugin` options just
like for the `migrate` command.

### Checking All Plugins at Once

The `--all` flag prints the status for the app and every loaded plugin that
ships migrations in a single call:

```bash
bin/cake migrations status --all
```

The default output is a compact summary listing only sections that need
action:

```text
Summary: 2 of 3 sections require action:
  - APP: 2 pending
  - Migrator: 1 pending
```

When everything is migrated, it collapses to a single
`Summary: all N sections are up to date.` line. Add `-v` to also print the
full per-section migration tables before the summary.

The exit code reflects the worst state across all sections — `0` when clean,
`3` (`CODE_STATUS_DOWN`) when migrations are pending, `2`
(`CODE_STATUS_MISSING`) when entries in the tracking table no longer have
matching files. This makes `status --all` directly usable as a deploy gate
in CI.

`--format json` returns one combined object keyed by section name,
e.g. `{"app": [...], "PluginName": [...]}`. `--all` cannot be combined with
`--plugin` or `--cleanup`.

### Cleaning Up Missing Migrations

Sometimes migration files may be deleted from the filesystem but still exist in
the migrations tracking table. These migrations will be marked as `MISSING` in
the status output. You can remove these entries using the `--cleanup` option:

```bash
bin/cake migrations status --cleanup
```

This will remove all migration entries from the tracking table that no longer
have corresponding migration files in the filesystem.

## Marking a Migration as Migrated

It can sometimes be useful to mark a set of migrations as migrated without
actually running them. In order to do this, use the `mark_migrated` command.

You can mark all migrations as migrated:

```bash
bin/cake migrations mark_migrated
```

You can also mark all migrations up to a specific version using the `--target`
option:

```bash
bin/cake migrations mark_migrated --target=20151016204000
```

If you do not want the targeted migration to be marked as migrated during the
process, use the `--exclude` flag:

```bash
bin/cake migrations mark_migrated --target=20151016204000 --exclude
```

If you wish to mark only the targeted migration as migrated, use the `--only`
flag:

```bash
bin/cake migrations mark_migrated --target=20151016204000 --only
```

You can also use the `--source`, `--connection`, and `--plugin` options just
like for the `migrate` command.

> [!NOTE]
> When you bake a snapshot with `cake bake migration_snapshot`, the created
> migration will automatically be marked as migrated. To prevent this behavior,
> for example for unit test migrations, use the `--generate-only` flag.

This command also accepts the migration version number as a positional
argument:

```bash
bin/cake migrations mark_migrated 20150420082532
```

If you wish to mark all migrations as migrated, you can use the `all` special
value:

```bash
bin/cake migrations mark_migrated all
```

## Seeding Your Database

Seed classes are a good way to populate your database with default or starter
data. They are also useful for generating data for development environments.

By default, seeds are looked for in the `config/Seeds/` directory of your
application. See [Database Seeding](../guides/seeding) for how to build and use seed
classes.
