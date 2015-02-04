# Database migrations plugin for CakePHP

[![Build Status](https://api.travis-ci.org/cakephp/migrations.png)](https://travis-ci.org/cakephp/migrations)
[![License](https://poser.pugx.org/cakephp/migrations/license.svg)](https://packagist.org/packages/cakephp/migrations)

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

This plugins provides the Migrations shell that you can invoke from your application's root folder:

```bash
$ bin/cake migrations
```

The command above will display a list of available options. Make sure you read [the official phinx documentation](http://docs.phinx.org/en/latest/migrations.html) to understand how migrations are created and executed in your database.

### Create a migration file with tables from your database

The [bake](https://github.com/cakephp/bake) command can be used to create a populated migration file based on the tables in your database:

```bash
$ bin/cake bake migration Initial [-p PluginName] [-c connection]
```

This will create a phinx file with tables found in your database. By default,
this will just add tables that have model files, but you can create a file with
all tables by adding the option `--checkModel false`.

### Create an empty migration file

To create an empty migration file, execute:

```bash
$ bin/cake migrations create Name
```

This will create a file under `config/Migrations` that you can edit to complete
the migration steps as documented in phinx's manual.

Please note that you will need to learn how to write your own migrations, you
need to fill in the up() and down() or change() methods if you want
automatically reversible migrations.

Once again, please make sure you read [the official phinx
documentation](http://docs.phinx.org/en/latest/migrations.html) to understand
how migrations are created and executed in your database.

### Run the migration

After modifying the migration file, you can run your changes in the database by executing:

```bash
$ bin/cake migrations migrate
```

### Rollback a migration

If you added any steps to revert a migration in the `down()` callback, you can execute this command and have that function executed:

```bash
$ bin/cake migrations rollback
```

### Watch migrations status

By executing this command you will have an overview of the migrations that have been executed and those still pending to be run:

```bash
$ bin/cake migrations status
```

### Usage for plugins

All the commands from above support the `--plugin` or `-p` option:

```bash
$ bin/cake migrations status -p PluginName
```

### Usage for connections

All the commands from above support the `--connection` or `-c` option:

```bash
$ bin/cake migrations migrate -c my_datasource
```

### Usage for custom primary key id in tables

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
