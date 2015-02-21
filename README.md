# cakephp/migrations [![Build Status](https://travis-ci.org/cakephp/migrations.svg?branch=master)](https://travis-ci.org/cakephp/migrations) [![License](https://poser.pugx.org/cakephp/migrations/license.svg)](https://packagist.org/packages/cakephp/migrations)

This is a pre-alpha version of a Database Migrations system for CakePHP 3.0. It is currently under development and should be considered experimental.

The plugin consists of a wrapper for the [phinx](http://phinx.org) migrations library.

## Installation

You can install this plugin into your CakePHP application using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

```javascript
"require": {
	"cakephp/migrations": "dev-master"
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
bin/cake bake migration Initial --snapshot

# Create an empty migration file
bin/cake bake migration AddFieldToTable

# You can specify a plugin to bake into
bin/cake bake migration AddFieldToTable --plugin PluginName

# You can specify an alternative connection when generating a migration.
bin/cake bake migration AddFieldToTable --connection connection

# Require that the table class exists before creating a migration
bin/cake bake migration AddFieldToTable --require-table
```

These commands will create a file under `config/Migrations` with the current database snapshot as the contents of the `change()` method. You may edit this as desired.

Please note that you will need to learn how to write your own migrations, you need to fill in the up() and down() or change() methods if you want automatically reversible migrations.

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
