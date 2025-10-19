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
``cake migration seed`` command. You should use this method to insert your test
data.

.. note::

    Unlike with migrations, seeds do not keep track of which seed classes have
    been run. This means database seeds can be run repeatedly. Keep this in
    mind when developing them.

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

.. note::

    Dependencies are only considered when executing all seed classes (default behavior).
    They won't be considered when running specific seed classes.


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

This is the easy part. To seed your database, simply use the ``migrations seed`` command:

.. code-block:: bash

        $ bin/cake migrations seed

By default, Migrations will execute all available seed classes. If you would like to
run a specific class, simply pass in the name of it using the ``--seed`` parameter.
You can use either the short name (without the ``Seed`` suffix) or the full name:

.. code-block:: bash

        $ bin/cake migrations seed --seed User
        # or
        $ bin/cake migrations seed --seed UserSeed

Both commands work identically.

You can also run multiple seeds:

.. code-block:: bash

        $ bin/cake migrations seed --seed User --seed Permission --seed Log
        # or with full names
        $ bin/cake migrations seed --seed UserSeed --seed PermissionSeed --seed LogSeed

You can also use the `-v` parameter for more output verbosity:

.. code-block:: bash

        $ bin/cake migrations seed -v

The Migrations seed functionality provides a simple mechanism to easily and repeatably
insert test data into your database, this is great for development environment
sample data or getting state for demos.
