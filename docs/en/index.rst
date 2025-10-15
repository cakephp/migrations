Migrations
##########

Migrations is a plugin that lets you track changes to your database schema over
time as PHP code that accompanies your application. This lets you ensure each
environment your application runs in can has the appropriate schema by applying
migrations.

Instead of writing schema modifications in SQL, this plugin allows you to
define schema changes with a high-level database portable API.

Installation
============

By default Migrations is installed with the application skeleton. If
you've removed it and want to re-install it, you can do so by running the
following from your application's ROOT directory (where **composer.json** file is
located):

.. code-block:: bash

    php composer.phar require cakephp/migrations "@stable"

    # Or if composer is installed globally
    composer require cakephp/migrations "@stable"

To use the plugin you'll need to load it in your application's
**config/bootstrap.php** file. You can use `CakePHP's Plugin shell
<https://book.cakephp.org/5/en/console-and-shells/plugin-shell.html>`__ to
load and unload plugins from your **config/bootstrap.php**:

.. code-block:: bash

    bin/cake plugin load Migrations

Or you can load the plugin by editing your **src/Application.php** file and
adding the following statement::

    $this->addPlugin('Migrations');

Additionally, you will need to configure the default database configuration for
your application in your **config/app.php** file as explained in the `Database
Configuration section
<https://book.cakephp.org/5/en/orm/database-basics.html#database-configuration>`__.

Overview
========

A migration is a PHP file that describes the changes to apply to your database.
A migration file can add, change or remove tables, columns, indexes and foreign keys.

If we wanted to create a table, we could use a migration similar to this::

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

When applied, this migration will add a table to your database named
``products`` with the following column definitions:

- ``id`` column of type ``integer`` as primary key. This column is added
  implicitly, but you can customize the name and type if necessary.
- ``name`` column of type ``string``
- ``description`` column of type ``text``
- ``created`` column of type ``datetime``
- ``modified`` column of type ``datetime``

.. note::

    Migrations are not automatically applied, you can apply and rollback
    migrations with CLI commands.

Once the file has been created in the **config/Migrations** folder, you can
apply it:

.. code-block:: bash

    bin/cake migrations migrate

Creating Migrations
===================

Migration files are stored in the **config/Migrations** directory of your
application. The name of the migration files are prefixed with the date in
which they were created, in the format **YYYYMMDDHHMMSS_MigrationName.php**.
Here are examples of migration filenames:

* **20160121163850_CreateProducts.php**
* **20160210133047_AddRatingToProducts.php**

The easiest way to create a migrations file is by using ``bin/cake bake
migration`` CLI command:

.. code-block:: bash

   bin/cake bake migration CreateProducts

This will create an empty migration that you can edit to add any columns,
indexes and foreign keys you need. See the :ref:`creating-a-table` section to
learn more about using migrations to define tables.

.. note::

    Migrations need to be applied using ``bin/cake migrations migrate`` after
    they have been created.

Migration file names
--------------------

When generating a migration, you can follow one of the following patterns
to have additional skeleton code generated:

* ``/^(Create)(.*)/`` Creates the specified table.
* ``/^(Drop)(.*)/`` Drops the specified table.
  Ignores specified field arguments
* ``/^(Add).*(?:To)(.*)/`` Adds fields to the specified
  table
* ``/^(Remove).*(?:From)(.*)/`` Removes fields from the
  specified table
* ``/^(Alter)(.*)/`` Alters the specified table. An alias
  for CreateTable and AddField.
* ``/^(Alter).*(?:On)(.*)/`` Alters fields from the specified table.

You can also use the ``underscore_form`` as the name for your migrations i.e.
``create_products``.

.. warning::

    Migration names are used as class names, and thus may collide with
    other migrations if the class names are not unique. In this case, it may be
    necessary to manually override the name at a later date, or simply change
    the name you are specifying.

Creating a table
----------------

You can use ``bake migration`` to create a table:

.. code-block:: bash

    bin/cake bake migration CreateProducts name:string description:text created modified

The command line above will generate a migration file that resembles::

    <?php
    use Migrations\BaseMigration;

    class CreateProducts extends BaseMigration
    {
        /**
         * Change Method.
         *
         * More information on this method is available here:
         * https://book.cakephp.org/migrations/3/en/writing-migrations.html#the-change-method
         * @return void
         */
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

Column syntax
-------------

The ``bake migration`` command provides a compact syntax to define columns when
generating a migration:

.. code-block:: bash

    bin/cake bake migration CreateProducts name:string description:text created modified

You can use the column syntax when creating tables and adding columns. You can
also edit the migration after generation to add or customize the columns

Columns on the command line follow the following pattern::

    fieldName:fieldType?[length]:indexType:indexName

For instance, the following are all valid ways of specifying an email field:

* ``email:string?``
* ``email:string:unique``
* ``email:string?[50]``
* ``email:string:unique:EMAIL_INDEX``
* ``email:string[120]:unique:EMAIL_INDEX``

While defining decimal columns, the ``length`` can be defined to have precision
and scale, separated by a comma.

* ``amount:decimal[5,2]``
* ``amount:decimal?[5,2]``

Columns with a question mark after the fieldType will make the column nullable.

The ``length`` part is optional and should always be written between bracket.

Fields named ``created`` and ``modified``, as well as any field with a ``_at``
suffix, will automatically be set to the type ``datetime``.

There are some heuristics to choosing fieldtypes when left unspecified or set to
an invalid value. Default field type is ``string``:

* id: integer
* created, modified, updated: datetime
* latitude, longitude (or short forms lat, lng): decimal

Additionally you can create an empty migrations file if you want full control
over what needs to be executed, by omitting to specify a columns definition:

.. code-block:: bash

    bin/cake migrations create MyCustomMigration


See :doc:`writing-migrations` for more information on how to use ``Table``
objects to interact with tables and define schema changes.

Adding columns to an existing table
-----------------------------------

If the migration name in the command line is of the form "AddXXXToYYY" and is
followed by a list of column names and types then a migration file containing
the code for creating the columns will be generated:

.. code-block:: bash

    bin/cake bake migration AddPriceToProducts price:decimal[5,2]

Executing the command line above will generate::

    <?php
    use Migrations\BaseMigration;

    class AddPriceToProducts extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('products');
            $table->addColumn('price', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 5,
                'scale' => 2,
            ]);
            $table->update();
        }
    }

Adding a column with an index
-----------------------------

It is also possible to add indexes to columns:

.. code-block:: bash

    bin/cake bake migration AddNameIndexToProducts name:string:index

will generate::

    <?php
    use Migrations\BaseMigration;

    class AddNameIndexToProducts extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('products');
            $table->addColumn('name', 'string')
                  ->addColumn('email', 'string')
                  ->addIndex(['name'])
                  // add a unique index:
                  ->addIndex('email', ['unique' => true])
                  ->update();
        }
    }

Altering a column
-----------------

In the same way, you can generate a migration to alter a column by using the
command line, if the migration name is of the form "AlterXXXOnYYY":

.. code-block:: bash

    bin/cake bake migration AlterPriceOnProducts name:float

will generate::

    <?php
    use Migrations\BaseMigration;

    class AlterPriceOnProducts extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('products');
            $table->changeColumn('name', 'float');
            $table->update();
        }
    }

.. warning::

    Changing the type of a column can result in data loss if the
    current and target column type are not compatible. For example converting
    a varchar to float.

Removing a column
-----------------

In the same way, you can generate a migration to remove a column by using the
command line, if the migration name is of the form "RemoveXXXFromYYY":

.. code-block:: bash

    bin/cake bake migration RemovePriceFromProducts price

creates the file::

    <?php
    use Migrations\BaseMigration;

    class RemovePriceFromProducts extends BaseMigration
    {
        public function up(): void
        {
            $table = $this->table('products');
            $table->removeColumn('price')
                  ->save();
        }
    }

.. note::

    The `removeColumn` command is not reversible, so must be called in the
    `up` method. A corresponding `addColumn` call should be added to the
    `down` method.

Generating migration snapshots from an existing database
========================================================

If you have a pre-existing database and want to start using
migrations, or to version control the initial schema of your application's
database, you can run the ``bake migration_snapshot`` command:

.. code-block:: bash

    bin/cake bake migration_snapshot Initial

It will generate a migration file called **YYYYMMDDHHMMSS_Initial.php**
containing all the create statements for all tables in your database.

By default, the snapshot will be created by connecting to the database defined
in the ``default`` connection configuration. If you need to bake a snapshot from
a different datasource, you can use the ``--connection`` option:

.. code-block:: bash

    bin/cake bake migration_snapshot Initial --connection my_other_connection

You can also make sure the snapshot includes only the tables for which you have
defined the corresponding model classes by using the ``--require-table`` flag:

.. code-block:: bash

    bin/cake bake migration_snapshot Initial --require-table

When using the ``--require-table`` flag, the shell will look through your
application ``Table`` classes and will only add the model tables in the snapshot.

If you want to generate a snapshot without marking it as migrated (for example,
for use in unit tests), you can use the ``--generate-only`` flag:

.. code-block:: bash

    bin/cake bake migration_snapshot Initial --generate-only

This will create the migration file but will not add an entry to the phinxlog
table, allowing you to move the file to a different location without causing
"MISSING" status issues.

The same logic will be applied implicitly if you wish to bake a snapshot for a
plugin. To do so, you need to use the ``--plugin`` option:

.. code-block:: bash

    bin/cake bake migration_snapshot Initial --plugin MyPlugin

Only the tables which have a ``Table`` object model class defined will be added
to the snapshot of your plugin.

.. note::

    When baking a snapshot for a plugin, the migration files will be created
    in your plugin's **config/Migrations** directory.

Be aware that when you bake a snapshot, it is automatically added to the
migrations log table as migrated.

Generating a diff
=================

As migrations are applied and rolled back, the migrations plugin will generate
a 'dump' file of your schema. If you make manual changes to your database schema
outside of migrations, you can use ``bake migration_diff`` to generate
a migration file that captures the difference between the current schema dump
file and database schema. To do so, you can use the following command:

.. code-block:: bash

    bin/cake bake migration_diff NameOfTheMigrations

By default, the diff will be created by connecting to the database defined
in the ``default`` connection configuration.
If you need to bake a diff from a different datasource, you can use the
``--connection`` option:

.. code-block:: bash

    bin/cake bake migration_diff NameOfTheMigrations --connection my_other_connection

If you want to use the diff feature on an application that already has a
migrations history, you need to manually create the dump file that will be used
as comparison:

.. code-block:: bash

    bin/cake migrations dump

The database state must be the same as it would be if you just migrated all
your migrations before you create a dump file.
Once the dump file is generated, you can start doing changes in your database
and use the ``bake migration_diff`` command whenever you see fit.

.. note::

    Migration diff generation can not detect column renamings.

Applying Migrations
===================

Once you have generated or written your migration file, you need to execute the
following command to apply the changes to your database:

.. code-block:: bash

    # Run all the migrations
    bin/cake migrations migrate

    # Migrate to a specific version using the ``--target`` option
    # or ``-t`` for short.
    # The value is the timestamp that is prefixed to the migrations file name::
    bin/cake migrations migrate -t 20150103081132

    # By default, migration files are looked for in the **config/Migrations**
    # directory. You can specify the directory using the ``--source`` option
    # or ``-s`` for short.
    # The following example will run migrations in the **config/Alternate**
    # directory
    bin/cake migrations migrate -s Alternate

    # You can run migrations to a different connection than the ``default`` one
    # using the ``--connection`` option or ``-c`` for short
    bin/cake migrations migrate -c my_custom_connection

    # Migrations can also be run for plugins. Simply use the ``--plugin`` option
    # or ``-p`` for short
    bin/cake migrations migrate -p MyAwesomePlugin

Reverting Migrations
====================

The rollback command is used to undo previous migrations executed by this
plugin. It is the reverse action of the ``migrate`` command:

.. code-block:: bash

    # You can rollback to the previous migration by using the
    # ``rollback`` command::
    bin/cake migrations rollback

    # You can also pass a migration version number to rollback
    # to a specific version::
    bin/cake migrations rollback -t 20150103081132

You can also use the ``--source``, ``--connection`` and ``--plugin`` options
just like for the ``migrate`` command.

View Migrations Status
======================

The Status command prints a list of all migrations, along with their current
status. You can use this command to determine which migrations have been run:

.. code-block:: bash

    bin/cake migrations status

You can also output the results as a JSON formatted string using the
``--format`` option (or ``-f`` for short):

.. code-block:: bash

     bin/cake migrations status --format json

You can also use the ``--source``, ``--connection`` and ``--plugin`` options
just like for the ``migrate`` command.

Cleaning up missing migrations
-------------------------------

Sometimes migration files may be deleted from the filesystem but still exist
in the phinxlog table. These migrations will be marked as **MISSING** in the
status output. You can remove these entries from the phinxlog table using the
``--cleanup`` option:

.. code-block:: bash

    bin/cake migrations status --cleanup

This will remove all migration entries from the phinxlog table that no longer
have corresponding migration files in the filesystem.

Marking a migration as migrated
===============================

It can sometimes be useful to mark a set of migrations as migrated without
actually running them. In order to do this, you can use the ``mark_migrated``
command. The command works seamlessly as the other commands.

You can mark all migrations as migrated using this command:

.. code-block:: bash

    bin/cake migrations mark_migrated

You can also mark all migrations up to a specific version as migrated using
the ``--target`` option:

.. code-block:: bash

    bin/cake migrations mark_migrated --target=20151016204000

If you do not want the targeted migration to be marked as migrated during the
process, you can use the ``--exclude`` flag with it:

.. code-block:: bash

    bin/cake migrations mark_migrated --target=20151016204000 --exclude

Finally, if you wish to mark only the targeted migration as migrated, you can
use the ``--only`` flag:

.. code-block:: bash

    bin/cake migrations mark_migrated --target=20151016204000 --only

You can also use the ``--source``, ``--connection`` and ``--plugin`` options
just like for the ``migrate`` command.

.. note::

    When you bake a snapshot with the ``cake bake migration_snapshot``
    command, the created migration will automatically be marked as migrated.
    To prevent this behavior (e.g., for unit test migrations), use the
    ``--generate-only`` flag.

This command expects the migration version number as argument:

.. code-block:: bash

    bin/cake migrations mark_migrated 20150420082532

If you wish to mark all migrations as migrated, you can use the ``all`` special
value. If you use it, it will mark all found migrations as migrated:

.. code-block:: bash

    bin/cake migrations mark_migrated all

Seeding your database
=====================

Seed classes are a good way to populate your database with default or starter
data. They are also a great way to generate data for development environments.

By default, seeds will be looked for in the ``config/Seeds/`` directory of
your application. See the :doc:`seeding` for how to build and use seed classes.

Generating a dump file
======================

The dump command creates a file to be used with the ``bake migration_diff``
command:

.. code-block:: bash

    bin/cake migrations dump

Each generated dump file is specific to the Connection it is generated from (and
is suffixed as such). This allows the ``bake migration_diff`` command to
properly compute diff in case your application is dealing with multiple database
possibly from different database vendors.

Dump files are created in the same directory as your migrations files.

You can also use the ``--source``, ``--connection`` and ``--plugin`` options
just like for the ``migrate`` command.


Using Migrations for Tests
==========================

If you are using migrations for your application schema you can also use those
same migrations to build schema in your tests. In your application's
``tests/bootstrap.php`` file you can use the ``Migrator`` class to build schema
when tests are run. The ``Migrator`` will use existing schema if it is current,
and if the migration history that is in the database differs from what is in the
filesystem, all tables will be dropped and migrations will be rerun from the
beginning::

    // in tests/bootstrap.php
    use Migrations\TestSuite\Migrator;

    $migrator = new Migrator();

    // Simple setup for with no plugins
    $migrator->run();

    // Run a non 'test' database
    $migrator->run(['connection' => 'test_other']);

    // Run migrations for plugins
    $migrator->run(['plugin' => 'Contacts']);

    // Run the Documents migrations on the test_docs connection.
    $migrator->run(['plugin' => 'Documents', 'connection' => 'test_docs']);


If you need to run multiple sets of migrations, those can be run as follows::

    // Run migrations for plugin Contacts on the ``test`` connection, and Documents on the ``test_docs`` connection
    $migrator->runMany([
        ['plugin' => 'Contacts'],
        ['plugin' => 'Documents', 'connection' => 'test_docs']
    ]);

If your database also contains tables that are not managed by your application
like those created by PostGIS, then you can exclude those tables from the drop
& truncate behavior using the ``skip`` option::

    $migrator->run(['connection' => 'test', 'skip' => ['postgis*']]);

The ``skip`` option accepts a ``fnmatch()`` compatible pattern to exclude tables
from drop & truncate operations.

If you need to see additional debugging output from migrations are being run,
you can enable a ``debug`` level logger.

Using Migrations In Plugins
===========================

Plugins can also provide migration files. This makes plugins that are intended
to be distributed much more portable and easy to install. All commands in the
Migrations plugin support the ``--plugin`` or ``-p`` option that will scope the
execution to the migrations relative to that plugin:

.. code-block:: bash

    bin/cake migrations status -p PluginName

    bin/cake migrations migrate -p PluginName

Running Migrations in a non-shell environment
=============================================

While typical usage of migrations is from the command line, you can also run
migrations from a non-shell environment, by using
``Migrations\Migrations`` class. This can be handy in case you are developing a plugin
installer for a CMS for instance. The ``Migrations`` class allows you to run the
following commands from the migrations shell:

* migrate
* rollback
* markMigrated
* status
* seed

Each of these commands has a method defined in the ``Migrations`` class.

Here is how to use it::

    use Migrations\Migrations;

    $migrations = new Migrations();

    // Will return an array of all migrations and their status
    $status = $migrations->status();

    // Will return true if success. If an error occurred, an exception will be thrown
    $migrate = $migrations->migrate();

    // Will return true if success. If an error occurred, an exception will be thrown
    $rollback = $migrations->rollback();

    // Will return true if success. If an error occurred, an exception will be thrown
    $markMigrated = $migrations->markMigrated(20150804222900);

    // Will return true if success. If an error occurred, an exception will be thrown
    $seeded = $migrations->seed();

The methods can accept an array of parameters that should match options from
the commands::

    use Migrations\Migrations;

    $migrations = new Migrations();

    // Will return an array of all migrations and their status
    $status = $migrations->status(['connection' => 'custom', 'source' => 'MyMigrationsFolder']);

You can pass any options the shell commands would take.
The only exception is the ``markMigrated`` command which is expecting the
version number of the migrations to mark as migrated as first argument. Pass
the array of parameters as the second argument for this method.

Optionally, you can pass these parameters in the constructor of the class.
They will be used as default and this will prevent you from having to pass
them on each method call::

    use Migrations\Migrations;

    $migrations = new Migrations(['connection' => 'custom', 'source' => 'MyMigrationsFolder']);

    // All the following calls will be done with the parameters passed to the Migrations class constructor
    $status = $migrations->status();
    $migrate = $migrations->migrate();

If you need to override one or more default parameters for one call, you can
pass them to the method::

    use Migrations\Migrations;

    $migrations = new Migrations(['connection' => 'custom', 'source' => 'MyMigrationsFolder']);

    // This call will be made with the "custom" connection
    $status = $migrations->status();
    // This one with the "default" connection
    $migrate = $migrations->migrate(['connection' => 'default']);

Feature Flags
=============

Migrations offers a few feature flags to compatibility with phinx. These features are disabled by default but can be enabled if required:

* ``unsigned_primary_keys``: Should Migrations create primary keys as unsigned integers? (default: ``false``)
* ``column_null_default``: Should Migrations create columns as null by default? (default: ``false``)
* ``add_timestamps_use_datetime``: Should Migrations use ``DATETIME`` type
  columns for the columns added by ``addTimestamps()``.

Set them via Configure to enable (e.g. in ``config/app.php``)::

    'Migrations' => [
        'unsigned_primary_keys' => true,
        'column_null_default' => true,
    ],

Skipping the ``schema.lock`` file generation
============================================

In order for the diff feature to work, a **.lock** file is generated everytime
you migrate, rollback or bake a snapshot, to keep track of the state of your
database schema at any given point in time. You can skip this file generation,
for instance when deploying on your production environment, by using the
``--no-lock`` option for the aforementioned command:

.. code-block:: bash

    bin/cake migrations migrate --no-lock

    bin/cake migrations rollback --no-lock

    bin/cake bake migration_snapshot MyMigration --no-lock

Deployment
==========

You should update your deployment scripts to run migrations when new code is
deployed. Ideally you want to run migrations after the code is on your servers,
but before the application code becomes active.

After running migrations remember to clear the ORM cache so it renews the column
metadata of your tables. Otherwise, you might end up having errors about
columns not existing when performing operations on those new columns. The
CakePHP Core includes a `Schema Cache Shell
<https://book.cakephp.org/5/en/console-and-shells/schema-cache.html>`__ that you
can use to perform this operation:

.. code-block:: bash

    bin/cake migration migrate
    bin/cake schema_cache clear

Alert of missing migrations
===========================

You can use the ``Migrations.PendingMigrations`` middleware in local development
to alert developers about new migrations that have not been applied::

    use Migrations\Middleware\PendingMigrationsMiddleware;

    $config = [
        'plugins' => [
            ... // Optionally include a list of plugins with migrations to check.
        ],
    ];

    $middlewareQueue
        ... // ErrorHandler middleware
        ->add(new PendingMigrationsMiddleware($config))
        ... // rest

You can add ``'app'`` config key set to ``false`` if you are only interested in
checking plugin migrations.

You can temporarily disable the migration check by adding
``skip-migration-check=1`` to the URL query string

IDE autocomplete support
========================

The `IdeHelper plugin
<https://github.com/dereuromark/cakephp-ide-helper>`__ can help
you to get more IDE support for the tables, their column names and possible column types.
Specifically PHPStorm understands the meta information and can help you autocomplete those.
