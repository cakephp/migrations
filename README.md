# Database migrations plugin for CakePHP 3.0

This is a pre-alpha version of a Database Migrations system for CakePHP 3.0.
It is currently under development and should be considered experimental.

The plugin merely consists in a wrapper for the [phinx](http://phinx.org) migrations
library.

## Installation

You can install this plugin into your CakePHP application using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

	"require": {
		"lorenzo/migrations": "dev-master"
	}

And run `php composer.phar update`

## Configuration

You will need to add the following line to your application's bootstrap.php file:

	Plugin::load('Migrations', ['namespace' => 'Cake\Migrations']);

Additionally, you will need to configure the `default` database configuration in your `Config/app.php` file.

## Usage

This plugins provides the Migrations shell that you can invoke from your application's src folder:

	$ Console/cake Migrations.migrations

The command above will display a list of available options. Make sure you read
[the official phinx documentation](http://docs.phinx.org/en/latest/migrations.html) to understand how migrations
are created and executed in your database.

### Create a migration file

Execute:

	$ Console/cake Migrations.migrations create Initial

This will create a file under `src/Config/Migrations` that you can edit to complete the migration steps as documented
in phinx's manual.

### Run the migration

After modifying the migration file, you can run your changes in the database by executing:

	$ Console/cake Migrations.migration migrate

### Rollback a migration

If you added any steps to revert a migration in the `down()` callback, you can execute this command
and have that function executed:

	$ Console/cake Migrations.migration rollback

### Watch migrations status

By executing this command you will have an overview of the migrations that have been executed and those
still pending to be run:

	$ Console/cake Migrations.migration status
