# Installation and Overview

This guide covers installation, plugin loading, and the basic migration model
used by the Migrations plugin.

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

Once the file has been created in the `config/Migrations` folder, you can apply
it:

```bash
bin/cake migrations migrate
```

## Next Steps

- Use [Creating Migrations](creating-migrations) to generate migration files
  with `bake`
- Use [Running and Managing Migrations](running-and-managing-migrations) to
  apply, roll back, and inspect migrations
- Use [Writing Migrations](../guides/writing-migrations) for the full Table API and
  migration authoring reference
