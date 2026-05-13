# Migration Methods

A migration declares its style by implementing one of two capability interfaces:

- `Migrations\ReversibleMigrationInterface` for migrations that define a
  single `change()` method.
- `Migrations\DirectionalMigrationInterface` for migrations that define
  separate `up()` and `down()` methods.

A migration implements **one** of the two, never both. Bake-generated
migrations already include the right `implements` clause. Migrations from
older versions of cakephp/migrations keep working without the interface
through a `method_exists()` fallback; see
[Upgrading to Capability Interfaces](/upgrades/upgrading-to-capability-interfaces)
for the adoption path.

## The Change Method

Migrations supports 'reversible migrations'. In many scenarios, you only need
to define the `up` logic, and Migrations can figure out how to generate the
rollback operations for you. For example:

```php
<?php

use Migrations\BaseMigration;
use Migrations\ReversibleMigrationInterface;

class CreateUserLoginsTable extends BaseMigration implements ReversibleMigrationInterface
{
    public function change(): void
    {
        // create the table
        $table = $this->table('user_logins');
        $table->addColumn('user_id', 'integer')
              ->addColumn('created', 'datetime')
              ->create();
    }
}
```

When executing this migration, Migrations will create the `user_logins` table
on the way up and automatically figure out how to drop the table on the way
down. Please be aware that when a `change` method exists, Migrations will
ignore the `up` and `down` methods. If you need to use these methods it is
recommended to create a separate migration file.

> [!NOTE]
> When creating or updating tables inside a `change()` method you must use the
> Table `create()` and `update()` methods. Migrations cannot automatically
> determine whether a `save()` call is creating a new table or modifying an
> existing one.

The following actions are reversible when done through the Table API in
Migrations, and will be automatically reversed:

- Creating a table
- Renaming a table
- Adding a column
- Renaming a column
- Adding an index
- Adding a foreign key
- Adding a check constraint

If a command cannot be reversed then Migrations will throw an
`IrreversibleMigrationException` when it's migrating down. If you wish to use a
command that cannot be reversed in the change function, you can use an `if`
statement with `$this->isMigratingUp()` to only run things in the up or down
direction. For example:

```php
<?php

use Migrations\BaseMigration;
use Migrations\ReversibleMigrationInterface;

class CreateUserLoginsTable extends BaseMigration implements ReversibleMigrationInterface
{
    public function change(): void
    {
        // create the table
        $table = $this->table('user_logins');
        $table->addColumn('user_id', 'integer')
              ->addColumn('created', 'datetime')
              ->create();
        if ($this->isMigratingUp()) {
            $table->insert([['user_id' => 1, 'created' => '2020-01-19 03:14:07']])
                  ->save();
        }
    }
}
```

## The Up Method

The `up()` method is automatically run by Migrations when you are migrating up
and it detects the given migration hasn't been executed previously. You should
use the `up()` method to transform the database with your intended changes.

## The Down Method

The `down()` method is automatically run by Migrations when you are migrating
down and it detects the given migration has been executed in the past. You
should use the `down()` method to reverse the transformations described in the
`up()` method.

## The Init Method

The `init()` method is run by Migrations before the migration methods if it
exists. This can be used for setting common class properties that are then used
within the migration methods.

## The Should Execute Method

The `shouldExecute()` method is run by Migrations before executing the
migration. This can be used to prevent the migration from being executed at
this time. It always returns `true` by default. You can override it in your
custom `BaseMigration` implementation.

## Working With Tables

The Table object enables you to manipulate database tables using PHP code. You
can retrieve an instance of the Table object by calling the `table()` method
from within your database migration:

```php
<?php

use Migrations\BaseMigration;
use Migrations\DirectionalMigrationInterface;

class MyNewMigration extends BaseMigration implements DirectionalMigrationInterface
{
    public function up(): void
    {
        $table = $this->table('tableName');
    }

    public function down(): void
    {
    }
}
```

You can then manipulate this table using the methods provided by the Table
object.

## Next Steps

- [Columns and Table Operations](columns-and-table-operations)
- [Indexes and Constraints](indexes-and-constraints)
- [Schema Introspection and Platform Limitations](schema-introspection-and-platform-limitations)
