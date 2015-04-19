# cakephp/migrations

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://img.shields.io/travis/cakephp/migrations/master.svg?style=flat-square)](https://travis-ci.org/cakephp/migrations)
[![Coverage Status](https://img.shields.io/coveralls/cakephp/migrations/master.svg?style=flat-square)](https://coveralls.io/r/cakephp/migrations?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/migrations.svg?style=flat-square)](https://packagist.org/packages/cakephp/migrations)

This is a Database Migrations system for CakePHP 3.0.

The plugin consists of a wrapper for the [phinx](http://phinx.org) migrations library.

## Installation

You can install this plugin into your CakePHP application using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

```javascript
"require": {
	"cakephp/migrations": "~1.0"
}
```

And run `php composer.phar update`

## Configuration

You will need to add the following line to your application's bootstrap.php file:

```php
Plugin::load('Migrations');
```

Additionally, you will need to configure the `default` database configuration in your `config/app.php` file.

## Usage

After creating/modifying a migration file, you can run your changes in the database by executing:

```bash
# The following will run migrations against the default database connection
bin/cake migrations migrate

# Rolling back migrations. If a `change()` method is defined, it will be reversed.
# Otherwise, the `down()` method will be invoked
bin/cake migrations rollback

# Retrieve the status of executed and pending migrations
bin/cake migrations status

# All console commands can take a `--plugin` or `-p` option
bin/cake migrations status -p PluginName

# You can also scope a command to a connection via the `--connection` or `-c` option
bin/cake migrations status -c my_datasource

# The following will mark targeted migration as marked without actually running it.
# The expected argument is the migration version number
bin/cake migrations mark_migrated 20150417223600
```

### Creating Migrations

This plugin provides two interfaces to creating migrations: a passthru to the Phinx library and a way to use the `bake` utility.

#### Phinx interface

The Phinx Migrations shell can be invoked via the following command from your application's root folder:

```bash
$ bin/cake migrations
```

The command above will display a list of available options. Make sure you read [the official phinx documentation](http://docs.phinx.org/en/latest/migrations.html) to understand how migrations are created and executed in your database.

Please note that you need to learn how to write your own migrations.

Empty migrations files will be created leaving you to fill in the up() and down() or change() if you want automatically reversible migrations.

Once again, please make sure you read [the official phinx documentation](http://docs.phinx.org/en/latest/migrations.html) to understand how migrations are created and executed in your database.


#### Bake interface

You can also use the `bake` command to generate migrations.

```bash
# The following will create an initial snapshot migration file:
bin/cake bake migration_snapshot Initial

# Create an empty migration file
bin/cake bake migration AddFieldToTable

# You can specify a plugin to bake into
bin/cake bake migration AddFieldToTable --plugin PluginName

# You can specify an alternative connection when generating a migration.
bin/cake bake migration AddFieldToTable --connection connection

# Require that the table class exists before creating a migration
bin/cake bake migration AddFieldToTable --require-table
```

These commands will create a file under `config/Migrations` with the current
database snapshot as the contents of the `change()` method. You may edit this
as desired.

Please note that you will need to learn how to write your own migrations, you
need to fill in the up() and down() or change() methods if you want
automatically reversible migrations.

Once again, please make sure you read [the official phinx documentation](http://docs.phinx.org/en/latest/migrations.html) to understand how migrations are created and executed in your database.

#### Usage for custom primary key id in tables

To create a table called `statuses` and use a CHAR (36) for the `id` field, this requires you to turn off the id.

See:

```php
$table = $this->table('statuses',
    [
        'id' => false,
        'primary_key' => ['id']
    ]);
$table->addColumn('id', 'char', ['limit' => 36])
    ->addColumn('name', 'char', ['limit' => 255])
    ->addColumn('model', 'string', ['limit' => 128])
    ->create();
```

> Phinx automatically creates an auto-increment `id` field for *every* table. This will hopefully be fixed in the future.

#### Generating Migrations from the CLI

> When using this option, you can still modify the migration before running them if so desired.

You can optionally generate entire migration files from the CLI without
interacting with the database or an editor. This functionality only works when
arguments are passed to the command `bin/cake bake generate` as follows:

```shell
bin/cake bake migration create_users name:string created modified

bin/cake bake migration alter_users name:string:index

bin/cake bake migration drop_users

bin/cake bake migration add_taxonomic_stuff_to_posts category:string tags:string

bin/cake bake migration remove_taxonomic_stuff_from_posts category tags
```

The above commands would:

- Create a users table with the fields [`id`, `name`, `created`, `modified`].
  A single primary key index would exist on `id` - as phinx autogenerates the
  field and it's index - and the `created` and `modified` fields would default
  to `datetime`, as per CakePHP conventions. Since the type is specified on
  `name`, it is string.
- Add an index to the `name` column in the `users` table.
- Drop the users table.
- Add `category` and `tags` fields to the `posts` table.
- Remove `category` and `tags` fields from the `posts` table.

Due to the conventions, not all schema changes can be performed via these shell commands.

Migration Names can follow any of the following patterns:

- *create_table* `/^(Create)(.*)/`: Creates the specified table
- *drop_table* `/^(Drop)(.*)/`: Drops the specified table. Ignores specified field arguments.
- *add_field* `/^(Add).*(?:To)(.*)/`: Adds fields to the specified table
- *remove_field* `/^(Remove).*(?:From)(.*)/`: Removes fields from the specified table
- *alter_table* `/^(Alter)(.*)/` : Alters the specified table. The
  *alter_table* command can be used as an alias for `CreateTable` and
  `AddField`.

Migration names are used as migration class names, and thus may collide with
other migrations if the class names are not unique. In this case, it may be
necessary to manually override the name at a later date, or simply change the
name you are specifying.

Fields are verified via the following the following regular expression:

    /^(\w*)(?::(\w*))?(?::(\w*))?(?::(\w*))?/

They follow the format:

    field:fieldType:indexType:indexName

For instance, the following are all valid ways of specifying the primary key `id`:

- `id:primary_key`
- `id:primary_key:primary`
- `id:integer:primary`
- `id:integer:primary:ID_INDEX`

Field types are those generically made available by phinx.

There are some heuristics to choosing fieldtypes when left unspecified or set to an invalid value:

- `id`: *integer*
- `created`, `modified`, `updated`: *datetime*
- Default *string*

Lengths for certain columns are also defaulted:

- *string*: `255`
- *integer*: `11`
- *biginteger*: `20`
