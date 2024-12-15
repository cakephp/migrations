Database Seeding
################

Seed classes are a great way to easily fill your database with data after
it's created. By default they are stored in the `seeds` directory; however, this
path can be changed in your configuration file.

.. note::

    Database seeding is entirely optional, and Migrations does not create a `Seeds`
    directory by default.

Creating a New Seed Class
=========================

Migrations includes a command to easily generate a new seed class:

.. code-block:: bash

        $ bin/cake bake seed MyNewSeeder

It is based on a skeleton template:

.. code-block:: php

        <?php

        use Migrations\BaseSeed;

        class MyNewSeeder extends BaseSeed
        {
            /**
             * Run Method.
             *
             * Write your database seeder using this method.
             *
             * More information on writing seeders is available here:
             * https://book.cakephp.org/migrations/5/en/seeding.html
             */
            public function run() : void
            {

            }
        }

The BaseSeed Class
======================

All Migrations seeds extend from the ``BaseSeed`` or ``AbstractSeed`` classes.
These classes provide the necessary support to create your seed classes. Seed
classes are primarily used to insert test data.

The Run Method
==============

The run method is automatically invoked by Migrations when you execute the
``cake migration seed`` command. You should use this method to insert your test
data.

.. note::

    Unlike with migrations, seeds do not keep track of which seed classes have
    been run. This means database seeders can be run repeatedly. Keep this in
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

Often you'll find that seeders need to run in a particular order, so they don't
violate foreign key constraints. To define this order, you can implement the
``getDependencies()`` method that returns an array of seeders to run before the
current seeder:

.. code-block:: php

        <?php

        use Migrations\BaseSeed;

        class ShoppingCartSeeder extends BaseSeed
        {
            public function getDependencies()
            {
                return [
                    'UserSeeder',
                    'ShopItemSeeder'
                ];
            }

            public function run() : void
            {
                // Seed the shopping cart  after the `UserSeeder` and
                // `ShopItemSeeder` have been run.
            }
        }

.. note::

    Dependencies are only considered when executing all seed classes (default behavior).
    They won't be considered when running specific seed classes.

Inserting Data
==============

Seed classes can also use the familiar ``Table`` object to insert data. You can
retrieve an instance of the Table object by calling the ``table()`` method from
within your seed class and then use the ``insert()`` method to insert data:

.. code-block:: php

        <?php

        use Migrations\BaseSeed;

        class PostsSeeder extends BaseSeed
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

        class UserSeeder extends BaseSeed
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
run a specific class, simply pass in the name of it using the ``--seed`` parameter:

.. code-block:: bash

        $ bin/cake migrations seed --seed UserSeeder

You can also run multiple seeders:

.. code-block:: bash

        $ bin/cake migrations seed --seed UserSeeder --seed PermissionSeeder --seed LogSeeder

You can also use the `-v` parameter for more output verbosity:

.. code-block:: bash

        $ bin/cake migrations seed -v

The Migrations seed functionality provides a simple mechanism to easily and repeatably
insert test data into your database, this is great for development environment
sample data or getting state for demos.
