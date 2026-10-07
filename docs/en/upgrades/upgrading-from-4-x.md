# Upgrading from 4.x to 5.x

Migrations 5.x includes significant changes from 4.x. This guide outlines
the breaking changes and what you need to update when upgrading.

## Requirements

- **PHP 8.2+** is now required (was PHP 8.1+)
- **CakePHP 5.3+** is now required
- **Phinx has been removed** - The builtin backend is now the only supported backend

If you were already using the builtin backend in 4.x (introduced in 4.3, default in 4.4),
the upgrade should be straightforward. See [Upgrading to the builtin backend](upgrading-to-builtin-backend) for more
details on API differences between the phinx and builtin backends.

## Command Changes

The phinx wrapper commands have been removed. The new command structure is:

### Migrations

The migration commands remain unchanged:

```bash
bin/cake migrations migrate
bin/cake migrations rollback
bin/cake migrations status
bin/cake migrations mark_migrated
bin/cake migrations dump
```

### Seeds

Seed commands have changed:

```bash
# 4.x                              # 5.x
bin/cake migrations seed           bin/cake seeds run
bin/cake migrations seed --seed X  bin/cake seeds run X
```

The new seed commands are:

- `bin/cake seeds run` - Run seed classes
- `bin/cake seeds run SeedName` - Run a specific seed
- `bin/cake seeds status` - Show seed execution status
- `bin/cake seeds reset` - Reset seed execution tracking

### Maintaining Backward Compatibility

If you need to maintain the old `migrations seed` command for existing scripts or
CI/CD pipelines, you can add command aliases in your `src/Application.php`:

```php
public function console(CommandCollection $commands): CommandCollection
{
    $commands = $this->addConsoleCommands($commands);

    // Add backward compatibility alias
    $commands->add('migrations seed', \Migrations\Command\SeedCommand::class);

    return $commands;
}
```

## Removed Classes and Namespaces

The following have been removed in 5.x:

- `Migrations\Command\Phinx\*` - All phinx wrapper commands
- `Migrations\Command\MigrationsCommand` - Use `bin/cake migrations` entry point
- `Migrations\Command\MigrationsSeedCommand` - Use `bin/cake seeds run`
- `Migrations\Command\MigrationsCacheBuildCommand` - Schema cache is managed differently
- `Migrations\Command\MigrationsCacheClearCommand` - Schema cache is managed differently
- `Migrations\Command\MigrationsCreateCommand` - Use `bin/cake bake migration`

If you have code that directly references any of these classes, you will need to update it.

## API Changes

### Adapter Query Results

If your migrations use `AdapterInterface::query()` to fetch rows, the return type has
changed from a phinx result to `Cake\Database\StatementInterface`:

```php
// 4.x (phinx)
$stmt = $this->getAdapter()->query('SELECT * FROM articles');
$rows = $stmt->fetchAll();
$row = $stmt->fetch();

// 5.x (builtin)
$stmt = $this->getAdapter()->query('SELECT * FROM articles');
$rows = $stmt->fetchAll('assoc');
$row = $stmt->fetch('assoc');
```

## New Features in 5.x

5.x includes several new features:

### Seed Tracking

Seeds are now tracked in a `cake_seeds` table by default, preventing accidental re-runs.
Use `--force` to run a seed again, or `bin/cake seeds reset` to clear tracking.
See [Database Seeding](../guides/seeding) for more details.

### Check Constraints

Support for database check constraints via `addCheckConstraint()`.
See [Writing Migrations](../guides/writing-migrations) for usage details.

### MySQL ALTER Options

Support for `ALGORITHM` and `LOCK` options on MySQL ALTER TABLE operations,
allowing control over how MySQL performs schema changes.

### insertOrSkip() for Seeds

New `insertOrSkip()` method for seeds to insert records only if they don't already exist,
making seeds more idempotent.

## Foreign Key Constraint Naming

Starting in 5.x, when you use `addForeignKey()` without providing an explicit constraint
name, migrations will auto-generate a name using the pattern `{table}_{columns}`.

Previously, MySQL would auto-generate constraint names (like `articles_ibfk_1`), while
PostgreSQL and SQL Server used migrations-generated names. Now all adapters use the same
consistent naming pattern.

**Impact on existing migrations:**

If you have existing migrations that use `addForeignKey()` without explicit names, and
later migrations that reference those constraints by name (e.g., in `dropForeignKey()`),
the generated names may differ between old and new migrations. This could cause
`dropForeignKey()` to fail if it's looking for a name that doesn't exist.

**Recommendations:**

1. For new migrations, you can rely on auto-generated names or provide explicit names
2. If you have rollback issues with existing migrations, you may need to update them
    to use explicit constraint names
3. The auto-generated names include conflict resolution - if `{table}_{columns}` already
    exists, a counter suffix is added (`_2`, `_3`, etc.)

**Name length limits:**

Auto-generated names are truncated to respect database limits:

- MySQL: 61 characters (64 - 3 for counter suffix)
- PostgreSQL: 60 characters (63 - 3)
- SQL Server: 125 characters (128 - 3)
- SQLite: No limit

## Migration File Compatibility

Your existing migration files should work without changes in most cases. The builtin backend
provides the same API as phinx for common operations.

If you encounter issues with existing migrations, please report them at
<https://github.com/cakephp/migrations/issues>
