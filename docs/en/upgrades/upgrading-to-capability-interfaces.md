# Upgrading to Capability Interfaces

Starting with 5.2.0, cakephp/migrations ships two capability interfaces that let migrations declare their style explicitly:

- `Migrations\ReversibleMigrationInterface` — for migrations that define a single reversible `change()` method.
- `Migrations\DirectionalMigrationInterface` — for migrations that define separate `up()` and `down()` methods.

A migration implements **one** of the two interfaces, never both.

## Why this exists

Until now, `Environment` dispatched migrations through `method_exists()` checks against `change()`, `up()`, and `down()`. This works at runtime but has a few drawbacks:

- A typo such as `function chnage()` silently no-ops at run time. The runtime cannot tell whether the migration is reversible or directional, so it just does nothing.
- IDEs and static analyzers cannot resolve `change()` / `up()` / `down()` on a generic `MigrationInterface`, so refactoring tools and PHPStan narrowing do not work.
- Custom runners that wrap `MigrationInterface` have to use reflection to figure out the migration style.

The capability interfaces are a way out of this without breaking existing code:

- `Environment` now dispatches via `instanceof` first and falls back to `method_exists()` for migrations that have not adopted the interfaces yet.
- The interfaces declare their method contracts as PHPDoc `@method` tags (not as real abstract methods), which means adding `implements` to an existing migration is a **zero-friction** change — your method signature is not validated against an abstract.

::: tip 5.x is a soft window
The PHPDoc-only contract is intentional. The 6.x release is expected to promote the `@method` tags to real abstract method declarations, at which point a missing or mistyped `change()` / `up()` / `down()` becomes a static error. The 5.next release gives you a runway to adopt the interfaces without breakage; the 6.x release tightens the contract.
:::

## What changed for app developers

If you do nothing, your existing migrations keep working. `Environment` retains a `method_exists()` fallback throughout the 5.x cycle.

Adopting the interfaces now is recommended because:

- New bakes already emit the right `implements` clause.
- Static analysis and IDE autocomplete start working on your migrations.
- Your app is upgrade-ready when 6.x lands.

## Per-app upgrade — manual edits

### Reversible migration (defines `change()`)

Before:

```php
<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateProducts extends BaseMigration
{
    public function change(): void
    {
        $this->table('products')
            ->addColumn('name', 'string')
            ->create();
    }
}
```

After:

```php
<?php
declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\ReversibleMigrationInterface;

class CreateProducts extends BaseMigration implements ReversibleMigrationInterface
{
    public function change(): void
    {
        $this->table('products')
            ->addColumn('name', 'string')
            ->create();
    }
}
```

### Directional migration (defines `up()` and `down()`)

Before:

```php
<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class BackfillOrderTotals extends BaseMigration
{
    public function up(): void
    {
        $this->execute('UPDATE orders SET total = ...');
    }

    public function down(): void
    {
        $this->execute('UPDATE orders SET total = NULL');
    }
}
```

After:

```php
<?php
declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\DirectionalMigrationInterface;

class BackfillOrderTotals extends BaseMigration implements DirectionalMigrationInterface
{
    public function up(): void
    {
        $this->execute('UPDATE orders SET total = ...');
    }

    public function down(): void
    {
        $this->execute('UPDATE orders SET total = NULL');
    }
}
```

### Anonymous migrations

Anonymous migrations get the same treatment:

```php
return new class extends BaseMigration implements ReversibleMigrationInterface
{
    public function change(): void
    {
    }
};
```

## Automated upgrade with rector

cakephp/migrations ships a rector rule that adds the right `implements` clause to every migration in your `config/Migrations/` folder.

Add the following to your `rector.php`:

```php
use Migrations\Rector\AddMigrationCapabilityInterfaceRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config/Migrations',
    ])
    ->withRules([
        AddMigrationCapabilityInterfaceRector::class,
    ]);
```

Then run rector:

```bash
vendor/bin/rector process --config=rector.php
```

What the rule does:

- For every class extending `Migrations\BaseMigration` (directly or transitively):
  - If the class defines `change()`, add `implements ReversibleMigrationInterface`.
  - If the class defines both `up()` and `down()`, add `implements DirectionalMigrationInterface`.
- One-way migrations that define only `up()` or only `down()` are left untouched. They keep working through the `method_exists()` fallback; adding `DirectionalMigrationInterface` would make `Environment` call the missing direction unconditionally and turn a rollback no-op into a fatal error.
- Classes that already implement either capability interface are skipped.
- Classes that define both `change()` and `up()`/`down()` are skipped — these are user errors that need a deliberate decision.

### Optional: combine with built-in rector rules

If you also want to normalize visibility and return types on your migration methods (the shape 6.x will expect), compose with rector's built-in sets:

```php
use Migrations\Rector\AddMigrationCapabilityInterfaceRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config/Migrations',
    ])
    ->withRules([
        AddMigrationCapabilityInterfaceRector::class,
    ])
    ->withSets([
        SetList::TYPE_DECLARATION,
    ]);
```

::: warning Only point rector at your migrations folder
Migration paths use scoped rector configs by default; pointing rector at `src/` or `tests/` will apply unrelated transformations. Keep the path list narrow.
:::

## Manual work that remains after rector

- **Migrations not on `BaseMigration`.** Anything still on a legacy Phinx `AbstractMigration` fork or a custom base that does not extend `BaseMigration` is skipped. Add the `implements` clause by hand.
- **Dynamically generated migration classes** (eval'd test fixtures, factories). Rector cannot see them. Add the `implements` clause at the generation site.
- **Custom base classes that themselves declare `change()` / `up()` / `down()`.** Rector adds the interface to the base class once. If the base lives in a third-party plugin you do not control, either implement the capability interface on the leaf class or PR the plugin upstream.
- **Bake-generated migrations from older versions.** Bake templates emit the `implements` clause out of the box from 5.next; older bakes do not. Rector cleans those up in one pass.

## Forward direction

The 6.x release is expected to:

- Promote the PHPDoc `@method` declarations on the capability interfaces to real abstract method declarations.
- Remove the `method_exists()` fallback in `Environment`.

Running rector now means the 6.x bump is a no-op for your migration files.
