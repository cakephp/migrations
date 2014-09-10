# Database migrations plugin for CakePHP 3.0

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

This plugins provides the Migrations shell that you can invoke from your application's src folder:

```bash
$ bin/cake Migrations.migrations
```

The command above will display a list of available options. Make sure you read [the official phinx documentation](http://docs.phinx.org/en/latest/migrations.html) to understand how migrations are created and executed in your database.

### Create a migration file

Execute:

```bash
$ bin/cake Migrations.migrations create Initial
```

This will create a file under `config/Migrations` that you can edit to complete the migration steps as documented in phinx's manual.

### Run the migration

After modifying the migration file, you can run your changes in the database by executing:

```bash
$ bin/cake Migrations.migrations migrate
```

### Rollback a migration

If you added any steps to revert a migration in the `down()` callback, you can execute this command and have that function executed:

```bash
$ bin/cake Migrations.migrations rollback
```

### Watch migrations status

By executing this command you will have an overview of the migrations that have been executed and those still pending to be run:

```bash
$ bin/cake Migrations.migrations status
```

### Usage for plugins

All the commands from above support the `--plugin` or `-p` option:

```bash
$ bin/cake Migrations.migrations status -p PluginName
```

### Usage for connections

All the commands from above support the `--connection` or `-c` option:

```bash
$ bin/cake Migrations.migrations migrate -c my_datasource
```
