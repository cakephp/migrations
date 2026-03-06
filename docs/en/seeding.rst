Database Seeding
################

Seed classes are a great way to easily fill your database with data after
it's created. By default, they are stored in the ``config/Seeds`` directory.

.. note::

    Database seeding is entirely optional, and Migrations does not create a Seeds
    directory by default.

Creating a New Seed Class
=========================

Migrations includes a command to easily generate a new seed class:

.. code-block:: bash

        $ bin/cake bake seed MyNewSeed

By default, it generates a traditional seed class with a named class:

.. code-block:: php

    <?php
    declare(strict_types=1);

    use Migrations\BaseSeed;

    class MyNewSeed extends BaseSeed
    {
        /**
         * Run Method.
         *
         * Write your database seed using this method.
         *
         * More information on writing seeds is available here:
         * https://book.cakephp.org/migrations/5/en/seeding.html
         */
        public function run() : void
        {

        }
    }

By default, the table the seed will try to alter is the "tableized" version of the seed filename.

Seed Naming
-----------

When referencing seeds in your code or via the command line, you can use either:

- The short name without the ``Seed`` suffix (e.g., ``Articles``, ``Users``)
- The full name with the ``Seed`` suffix (e.g., ``ArticlesSeed``, ``UsersSeed``)

Both forms work identically in:

- Command line: ``--seed Articles`` or ``--seed ArticlesSeed``
- Dependencies: ``return ['User', 'ShopItem'];`` or ``return ['UserSeed', 'ShopItemSeed'];``
- Calling seeds: ``$this->call('Articles');`` or ``$this->call('ArticlesSeed');``

Using the short name is recommended for cleaner, more concise code.

Anonymous Seed Classes
----------------------

Migrations also supports generating anonymous seed classes, which use PHP's
anonymous class feature instead of named classes. This style is useful for:

- Avoiding namespace declarations
- Better PHPCS compatibility (no class name to filename matching required)
- Simpler file structure without named class constraints

To generate an anonymous seed class, use the ``--style anonymous`` option:

.. code-block:: bash

    $ bin/cake bake seed MyNewSeed --style anonymous

This generates a seed file using an anonymous class:

.. code-block:: php

    <?php
    declare(strict_types=1);

    use Migrations\BaseSeed;

    return new class extends BaseSeed
    {
        /**
         * Run Method.
         *
         * Write your database seeder using this method.
         *
         * More information on writing seeds is available here:
         * https://book.cakephp.org/migrations/5/en/seeding.html
         */
        public function run(): void
        {

        }
    };

Both traditional and anonymous seed classes work identically at runtime and can
be used interchangeably within the same project.

You can set the default seed style globally in your application configuration:

.. code-block:: php

    // In config/app.php or config/app_local.php
    'Migrations' => [
        'style' => 'anonymous',  // or 'traditional'
    ],

Seed Options
------------

.. code-block:: bash
    # You specify the name of the table the seed files will alter by using the ``--table`` option
    bin/cake bake seed Articles --table my_articles_table

    # You can specify a plugin to bake into
    bin/cake bake seed Articles --plugin PluginName

    # You can specify an alternative connection when generating a seed.
    bin/cake bake seed Articles --connection connection

    # Include data from the Articles table in your seed.
    bin/cake bake seed --data Articles

By default, it will export all the rows found in your table. You can limit the
number of rows exported by using the ``--limit`` option:

.. code-block:: bash

    # Will only export the first 10 rows found
    bin/cake bake seed --data --limit 10 Articles

If you only want to include a selection of fields from the table in your seed
file, you can use the ``--fields`` option. It takes the list of fields to
include as a comma separated value string:

.. code-block:: bash

    # Will only export the fields `id`, `title` and `excerpt`
    bin/cake bake seed --data --fields id,title,excerpt Articles

.. tip::

    Of course you can use both the ``--limit`` and ``--fields`` options in the
    same command call.

.. _custom-seed-migration-templates:

Customizing Seed and Migration templates
----------------------------------------

Because migrations uses `bake <https://book.cakephp.org/bake>`__ under the hood
you can customize the templates that migrations uses for creating seeds and
migrations by creating templates in your application. Custom templates for
migrations should be on one of the following paths:

- ``ROOT/templates/plugin/Migrations/bake/``
- ``ROOT/templates/bake/``

For example, the seed templates are:

- Traditional: ``Seed/seed.twig`` at **ROOT/templates/plugin/Migrations/bake/Seed/seed.twig**
- Anonymous: ``Seed/seed-anonymous.twig`` at **ROOT/templates/plugin/Migrations/bake/Seed/seed-anonymous.twig**

The BaseSeed Class
==================

All Migrations seeds extend from the ``BaseSeed`` class.
It provides the necessary support to create your seed classes. Seed
classes are primarily used to insert test data.

The Run Method
==============

The run method is automatically invoked by Migrations when you execute the
``cake seeds run`` command. You should use this method to insert your test
data.

Seed Execution Tracking
========================

Seeds track their execution state in the ``cake_seeds`` database table. By default,
a seed will only run once. If you attempt to run a seed that has already been
executed, it will be skipped with an "already executed" message.

To re-run a seed that has already been executed, use the ``--force`` flag:

.. code-block:: bash

    bin/cake seeds run Users --force

You can check which seeds have been executed using the status command:

.. code-block:: bash

    bin/cake seeds status

To reset all seeds' execution state (allowing them to run again without ``--force``):

.. code-block:: bash

    bin/cake seeds reset

.. note::

    When re-running seeds with ``--force``, be careful to ensure your seeds are
    idempotent (safe to run multiple times) or they may create duplicate data.

Customizing the Seed Tracking Table
------------------------------------

By default, seed execution is tracked in a table named ``cake_seeds``. You can
customize this table name by configuring it in your ``config/app.php`` or
``config/app_local.php``:

.. code-block:: php

    'Migrations' => [
        'seed_table' => 'my_custom_seeds_table',
    ],

This is useful if you need to avoid table name conflicts or want to follow
a specific naming convention in your database.

Idempotent Seeds
================

Some seeds are designed to be run multiple times safely (idempotent), such as seeds
that update configuration or reference data. For these seeds, you can override the
``isIdempotent()`` method:

.. code-block:: php

    <?php
    declare(strict_types=1);

    use Migrations\BaseSeed;

    class ConfigSeed extends BaseSeed
    {
        /**
         * Mark this seed as idempotent.
         * It will run every time it is invoked.
         */
        public function isIdempotent(): bool
        {
            return true;
        }

        public function run(): void
        {
            // This seed will run every time, so make it safe to run multiple times
            $this->execute("
                INSERT INTO settings (setting_key, setting_value)
                VALUES ('app_version', '2.0.0')
                ON DUPLICATE KEY UPDATE setting_value = '2.0.0'
            ");

            // Or check before inserting
            $exists = $this->fetchRow(
                "SELECT COUNT(*) as count FROM settings WHERE setting_key = 'maintenance_mode'"
            );

            if ($exists['count'] == 0) {
                $this->table('settings')->insert([
                    'setting_key' => 'maintenance_mode',
                    'setting_value' => 'false',
                ])->save();
            }
        }
    }

When ``isIdempotent()`` returns ``true``:

- The seed will run **every time** you execute ``seeds run``
- The last execution time is still tracked in the ``cake_seeds`` table
- The ``seeds status`` command will show the seed as ``(idempotent)``
- You must ensure the seed's ``run()`` method handles duplicate executions safely

This is useful for:

- Configuration seeds that should always reflect current values
- Reference data that may need periodic updates
- Seeds that use ``INSERT ... ON DUPLICATE KEY UPDATE`` or similar patterns
- Development/testing seeds that need to run repeatedly

.. warning::

    Only mark a seed as idempotent if you've verified it's safe to run multiple times.
    Otherwise, you may create duplicate data or other unexpected behavior.

The Init Method
===============

The ``init()`` method is run by Migrations before the run method if it exists. This
can be used to initialize properties of the Seed class before using run.

The Should Execute Method
=========================

The ``shouldExecute()`` method is run by Migrations before executing the seed.
This can be used to prevent the seed from being executed at this time. It always
returns true by default. You can override it in your custom ``BaseSeed``
implementation.

Foreign Key Dependencies
========================

Often you'll find that seeds need to run in a particular order, so they don't
violate foreign key constraints. To define this order, you can implement the
``getDependencies()`` method that returns an array of seeds to run before the
current seed:

.. code-block:: php

    <?php

    use Migrations\BaseSeed;

    class ShoppingCartSeed extends BaseSeed
    {
        public function getDependencies(): array
        {
            return [
                'User',      // Short name without 'Seed' suffix
                'ShopItem',  // Short name without 'Seed' suffix
            ];
        }

        public function run() : void
        {
            // Seed the shopping cart after the `UserSeed` and
            // `ShopItemSeed` have been run.
        }
    }

You can also use the full seed name including the ``Seed`` suffix:

.. code-block:: php

    return [
        'UserSeed',
        'ShopItemSeed',
    ];

Both forms are supported and work identically.

Automatic Dependency Execution
-------------------------------

When you run a seed that has dependencies, the system will automatically check if
those dependencies have been executed. If any dependencies haven't run yet, they
will be executed automatically before the current seed runs. This ensures proper
execution order and prevents foreign key constraint violations.

For example, if you run:

.. code-block:: bash

    bin/cake seeds run ShoppingCartSeed

And ``ShoppingCartSeed`` depends on ``UserSeed`` and ``ShopItemSeed``, the system
will automatically execute those dependencies first if they haven't been run yet.

.. note::

    Dependencies that have already been executed (according to the ``cake_seeds``
    table) will be skipped, unless you use the ``--force`` flag which will
    re-execute all seeds including dependencies.


Calling a Seed from another Seed
================================

Usually when seeding, the order in which to insert the data must be respected
to not encounter constraint violations. Since seeds are executed in an
alphabetical order by default, you can use the ``\Migrations\BaseSeed::call()``
method to define your own sequence of seeds execution:

.. code-block:: php

    <?php

    use Migrations\BaseSeed;

    class DatabaseSeed extends BaseSeed
    {
        public function run(): void
        {
            $this->call('Another');      // Short name without 'Seed' suffix
            $this->call('YetAnother');   // Short name without 'Seed' suffix

            // You can use the plugin dot syntax to call seeds from a plugin
            $this->call('PluginName.FromPlugin');
        }
    }

You can also use the full seed name including the ``Seed`` suffix:

.. code-block:: php

    $this->call('AnotherSeed');
    $this->call('YetAnotherSeed');
    $this->call('PluginName.FromPluginSeed');

Both forms are supported and work identically.

Inserting Data
==============

Seed classes can also use the familiar ``Table`` object to insert data. You can
retrieve an instance of the Table object by calling the ``table()`` method from
within your seed class and then use the ``insert()`` method to insert data:

.. code-block:: php

    <?php

    use Migrations\BaseSeed;

    class PostsSeed extends BaseSeed
    {
        public function run() : void
        {
            $data = [
                [
                    'body'    => 'foo',
                    'created' => date('Y-m-d H:i:s'),
                ],[
                    'body'    => 'bar',
                    'created' => date('Y-m-d H:i:s'),
                ]
            ];

            $posts = $this->table('posts');
            $posts->insert($data)
                  ->saveData();
        }
    }

.. note::

    You must call the ``saveData()`` method to commit your data to the table.
    Migrations will buffer data until you do so.

Insert Modes
============

In addition to the standard ``insert()`` method, Migrations provides specialized
insert methods for handling conflicts with existing data.

Insert or Skip
--------------

The ``insertOrSkip()`` method inserts rows but silently skips any that would
violate a unique constraint:

.. code-block:: php

    <?php

    use Migrations\BaseSeed;

    class CurrencySeed extends BaseSeed
    {
        public function run(): void
        {
            $data = [
                ['code' => 'USD', 'name' => 'US Dollar'],
                ['code' => 'EUR', 'name' => 'Euro'],
            ];

            $this->table('currencies')
                ->insertOrSkip($data)
                ->saveData();
        }
    }

Insert or Update (Upsert)
-------------------------

The ``insertOrUpdate()`` method performs an "upsert" operation - inserting new
rows and updating existing rows that conflict on unique columns:

.. code-block:: php

    <?php

    use Migrations\BaseSeed;

    class ExchangeRateSeed extends BaseSeed
    {
        public function run(): void
        {
            $data = [
                ['code' => 'USD', 'rate' => 1.0000],
                ['code' => 'EUR', 'rate' => 0.9234],
            ];

            $this->table('exchange_rates')
                ->insertOrUpdate($data, ['rate'], ['code'])
                ->saveData();
        }
    }

The method takes three arguments:

- ``$data``: The rows to insert (same format as ``insert()``)
- ``$updateColumns``: Which columns to update when a conflict occurs
- ``$conflictColumns``: Which columns define uniqueness (must have a unique index)

.. warning::

    Database-specific behavior differences:

    **MySQL**: Uses ``ON DUPLICATE KEY UPDATE``. The ``$conflictColumns`` parameter
    is ignored because MySQL automatically applies the update to *all* unique
    constraint violations on the table. Passing ``$conflictColumns`` will trigger
    a warning. If your table has multiple unique constraints, be aware that a
    conflict on *any* of them will trigger the update.

    **PostgreSQL/SQLite**: Uses ``ON CONFLICT (...) DO UPDATE SET``. The
    ``$conflictColumns`` parameter is required and specifies exactly which unique
    constraint should trigger the update. A ``RuntimeException`` will be thrown
    if this parameter is empty.

    **SQL Server**: Not currently supported. Use separate insert/update logic.

Truncating Tables
=================

In addition to inserting data Migrations makes it trivial to empty your tables using the
SQL `TRUNCATE` command:

.. code-block:: php

    <?php

    use Migrations\BaseSeed;

    class UserSeed extends BaseSeed
    {
        public function run() : void
        {
            $data = [
                [
                    'body'    => 'foo',
                    'created' => date('Y-m-d H:i:s'),
                ],
                [
                    'body'    => 'bar',
                    'created' => date('Y-m-d H:i:s'),
                ]
            ];

            $posts = $this->table('posts');
            $posts->insert($data)
                  ->saveData();

            // empty the table
            $posts->truncate();
        }
    }

.. note::

    SQLite doesn't natively support the ``TRUNCATE`` command so behind the scenes
    ``DELETE FROM`` is used. It is recommended to call the ``VACUUM`` command
    after truncating a table. Migrations does not do this automatically.

Executing Seed Classes
======================

This is the easy part. To seed your database, simply use the ``seeds run`` command:

.. code-block:: bash

        $ bin/cake seeds run

By default, Migrations will execute all available seed classes. If you would like to
run a specific seed, simply pass in the seed name as an argument.
You can use either the short name (without the ``Seed`` suffix) or the full name:

.. code-block:: bash

        $ bin/cake seeds run User
        # or
        $ bin/cake seeds run UserSeed

Both commands work identically.

You can also run multiple seeds by separating them with commas:

.. code-block:: bash

        $ bin/cake seeds run User,Permission,Log
        # or with full names
        $ bin/cake seeds run UserSeed,PermissionSeed,LogSeed

You can also use the `-v` parameter for more output verbosity:

.. code-block:: bash

        $ bin/cake seeds run -v

The Migrations seed functionality provides a simple mechanism to easily and repeatably
insert test data into your database, this is great for development environment
sample data or getting state for demos.
