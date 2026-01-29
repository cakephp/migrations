Writing Migrations
##################

Migrations are a declarative API that helps you transform your database. Each migration
is represented by a PHP class in a unique file. It is preferred that you write
your migrations using the Migrations API, but raw SQL is also supported.

Creating a New Migration
========================

Let's start by creating a new migration with ``bake``:

.. code-block:: bash

        $ bin/cake bake migration

This will create a new migration in the format
``YYYYMMDDHHMMSS_my_new_migration.php``, where the first 14 characters are
replaced with the current timestamp down to the second.

If you have specified multiple migration paths, you will be asked to select
which path to create the new migration in.

Bake will automatically creates a skeleton migration file with a single method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Change Method.
         *
         * Write your reversible migrations in this method.
         */
        public function change(): void
        {
        }

    }


The Change Method
=================

Migrations supports 'reversible migrations'. In many scenarios, you
only need to define the ``up`` logic, and Migrations can figure out how to
generate the rollback operations for you. For example:

.. code-block:: php

        <?php

        use Migrations\BaseMigration;

        class CreateUserLoginsTable extends BaseMigration
        {
            public function change(): void
            {
                // create the table
                $table = $this->table('user_logins');
                $table->addColumn('user_id', 'integer')
                      ->addColumn('created', 'datetime')
                      ->create();
            }
        }

When executing this migration, Migrations will create the ``user_logins`` table on
the way up and automatically figure out how to drop the table on the way down.
Please be aware that when a ``change`` method exists, Migrations will
ignore the ``up`` and ``down`` methods. If you need to use these methods it is
recommended to create a separate migration file.

.. note::

    When creating or updating tables inside a ``change()`` method you must use
    the Table ``create()`` and ``update()`` methods. Migrations cannot automatically
    determine whether a ``save()`` call is creating a new table or modifying an
    existing one.

The following actions are reversible when done through the Table API in
Migrations, and will be automatically reversed:

- Creating a table
- Renaming a table
- Adding a column
- Renaming a column
- Adding an index
- Adding a foreign key
- Adding a check constraint

If a command cannot be reversed then Migrations will throw an
``IrreversibleMigrationException`` when it's migrating down. If you wish to
use a command that cannot be reversed in the change function, you can use an
if statement with  ``$this->isMigratingUp()`` to only run things in the
up or down direction. For example::

    <?php

    use Migrations\BaseMigration;

    class CreateUserLoginsTable extends BaseMigration
    {
        public function change(): void
        {
            // create the table
            $table = $this->table('user_logins');
            $table->addColumn('user_id', 'integer')
                  ->addColumn('created', 'datetime')
                  ->create();
            if ($this->isMigratingUp()) {
                $table->insert([['user_id' => 1, 'created' => '2020-01-19 03:14:07']])
                      ->save();
            }
        }
    }


The Up Method
=============

The up method is automatically run by Migrations when you are migrating up and it
detects the given migration hasn't been executed previously. You should use the
up method to transform the database with your intended changes.

The Down Method
===============

The down method is automatically run by Migrations when you are migrating down and
it detects the given migration has been executed in the past. You should use
the down method to reverse/undo the transformations described in the up method.

The Init Method
===============

The ``init()`` method is run by Migrations before the migration methods if it exists.
This can be used for setting common class properties that are then used within
the migration methods.

The Should Execute Method
=========================

The ``shouldExecute()`` method is run by Migrations before executing the migration.
This can be used to prevent the migration from being executed at this time. It always
returns true by default. You can override it in your custom ``BaseMigration``
implementation.

Working With Tables
===================

The Table object enables you to easily manipulate database tables using PHP
code. You can retrieve an instance of the Table object by calling the
``table()`` method from within your database migration::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('tableName');
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

You can then manipulate this table using the methods provided by the Table
object.

.. _adding-columns:

Adding Columns
==============

Column types are specified as strings and can be one of:

-  binary
-  boolean
-  char
-  date
-  datetime
-  decimal
-  float
-  double
-  smallinteger
-  integer
-  biginteger
-  string
-  text
-  time
-  timestamp
-  uuid
-  binaryuuid
-  nativeuuid

In addition, the MySQL adapter supports ``enum``, ``set``, ``blob``,
``tinyblob``, ``mediumblob``, ``longblob``, ``bit`` and ``json`` column types
(``json`` in MySQL 5.7 and above). When providing a limit value and using
``binary``, ``varbinary`` or ``blob`` and its subtypes, the retained column type
will be based on required length (see `Limit Option and MySQL`_ for details).

With most adapters, the ``uuid`` and ``nativeuuid`` column types are aliases,
however with the MySQL adapter + MariaDB, the ``nativeuuid`` type maps to
a native uuid column instead of ``CHAR(36)`` like ``uuid`` does.

In addition, the Postgres adapter supports ``interval``, ``json``, ``jsonb``,
``uuid``, ``cidr``, ``inet`` and ``macaddr`` column types (PostgreSQL 9.3 and
above).

Valid Column Options
--------------------

The following are valid column options:

For any column type:

======= ===========
Option  Description
======= ===========
limit   set maximum length for strings, also hints column types in adapters (see note below)
length  alias for ``limit``
default set default value or action
null    allow ``NULL`` values, defaults to ``true`` (setting ``identity`` will override default to ``false``)
after   specify the column that a new column should be placed after, or use ``\Migrations\Db\Adapter\MysqlAdapter::FIRST`` to place the column at the start of the table *(only applies to MySQL)*
comment set a text comment on the column
======= ===========

For ``decimal`` and ``float`` columns:

========= ===========
Option    Description
========= ===========
precision total number of digits (e.g., 10 in ``DECIMAL(10,2)``)
scale     number of digits after the decimal point (e.g., 2 in ``DECIMAL(10,2)``)
signed    enable or disable the ``unsigned`` option *(only applies to MySQL)*
========= ===========

.. note::

    **Precision and Scale Terminology**

    Migrations follows the SQL standard where ``precision`` represents the total number of digits,
    and ``scale`` represents digits after the decimal point. For example, to create ``DECIMAL(10,2)``
    (10 total digits with 2 decimal places):

    .. code-block:: php

        $table->addColumn('price', 'decimal', [
            'precision' => 10,  // Total digits
            'scale' => 2,       // Decimal places
        ]);

    This differs from CakePHP's TableSchema which uses ``length`` for total digits and
    ``precision`` for decimal places. The migration adapter handles this conversion automatically.

For ``enum`` and ``set`` columns:

========= ===========
Option    Description
========= ===========
values    Can be a comma separated list or an array of values
========= ===========

For ``smallinteger``, ``integer`` and ``biginteger`` columns:

======== ===========
Option   Description
======== ===========
identity enable or disable automatic incrementing (if enabled, will set ``null: false`` if ``null`` option is not set)
signed   enable or disable the ``unsigned`` option *(only applies to MySQL)*
========

For Postgres, when using ``identity``, it will utilize the ``serial`` type
appropriate for the integer size, so that ``smallinteger`` will give you
``smallserial``, ``integer`` gives ``serial``, and ``biginteger`` gives
``bigserial``.

For ``date`` columns:

======== ===========
Option   Description
======== ===========
default  set default value (use with ``CURRENT_DATE``)
======== ===========

For ``time`` columns:

======== ===========
Option   Description
======== ===========
default  set default value (use with ``CURRENT_TIME``)
timezone enable or disable the ``with time zone`` option *(only applies to Postgres)*
======== ===========

For ``datetime`` columns:

======== ===========
Option   Description
======== ===========
default  set default value (use with ``CURRENT_TIMESTAMP``)
timezone enable or disable the ``with time zone`` option *(only applies to Postgres)*
======== ===========

For ``timestamp`` columns:

======== ===========
Option   Description
======== ===========
default  set default value (use with ``CURRENT_TIMESTAMP``)
update   set an action to be triggered when the row is updated (use with ``CURRENT_TIMESTAMP``) *(only applies to MySQL)*
timezone enable or disable the ``with time zone`` option for ``time`` and ``timestamp`` columns *(only applies to Postgres)*
======== ===========

You can add ``created`` and ``updated`` timestamps to a table using the
``addTimestamps()`` method. This method accepts three arguments, where the first
two allow setting alternative names for the columns while the third argument
allows you to enable the ``timezone`` option for the columns. The defaults for
these arguments are ``created``, ``updated``, and ``false`` respectively. For
the first and second argument, if you provide ``null``, then the default name
will be used, and if you provide ``false``, then that column will not be
created. Please note that attempting to set both to ``false`` will throw
a ``\RuntimeException``. Additionally, you can use the
``addTimestampsWithTimezone()`` method, which is an alias to ``addTimestamps()``
that will always set the third argument to ``true`` (see examples below). The
``created`` column will have a default set to ``CURRENT_TIMESTAMP``. For MySQL
only, ``updated`` column will have update set to
``CURRENT_TIMESTAMP``::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Change.
         */
        public function change(): void
        {
            // Use defaults (without timezones)
            $table = $this->table('users')->addTimestamps()->create();
            // Use defaults (with timezones)
            $table = $this->table('users')->addTimestampsWithTimezone()->create();

            // Override the 'created' column name with 'recorded_at'.
            $table = $this->table('books')->addTimestamps('recorded_at')->create();

            // Override the 'updated' column name with 'amended_at', preserving timezones.
            // The two lines below do the same, the second one is simply cleaner.
            $table = $this->table('books')->addTimestamps(null, 'amended_at', true)->create();
            $table = $this->table('users')->addTimestampsWithTimezone(null, 'amended_at')->create();

            // Only add the created column to the table
            $table = $this->table('books')->addTimestamps(null, false);
            // Only add the updated column to the table
            $table = $this->table('users')->addTimestamps(false);
            // Note, setting both false will throw a \RuntimeError
        }
    }

For ``boolean`` columns:

======== ===========
Option   Description
======== ===========
signed   enable or disable the ``unsigned`` option *(only applies to MySQL)*
======== ===========

For ``string`` and ``text`` columns:

========= ===========
Option    Description
========= ===========
collation set collation that differs from table defaults *(only applies to MySQL)*
encoding  set character set that differs from table defaults *(only applies to MySQL)*
========= ===========

Limit Option and MySQL
----------------------

When using the MySQL adapter, there are a couple things to consider when working with limits:

- When using a ``string`` primary key or index on MySQL 5.7 or below, or the
  MyISAM storage engine, and the default charset of ``utf8mb4_unicode_ci``, you
  must specify a limit less than or equal to 191, or use a different charset.
- Additional hinting of database column type can be made for ``integer``,
  ``text``, ``blob``, ``tinyblob``, ``mediumblob``, ``longblob`` columns. Using
  ``limit`` with one the following options will modify the column type
  accordingly:

============ ==============
Limit        Column Type
============ ==============
BLOB_TINY    TINYBLOB
BLOB_REGULAR BLOB
BLOB_MEDIUM  MEDIUMBLOB
BLOB_LONG    LONGBLOB
TEXT_TINY    TINYTEXT
TEXT_REGULAR TEXT
TEXT_MEDIUM  MEDIUMTEXT
TEXT_LONG    LONGTEXT
INT_TINY     TINYINT
INT_SMALL    SMALLINT
INT_MEDIUM   MEDIUMINT
INT_REGULAR  INT
INT_BIG      BIGINT
============ ==============

For ``binary`` or ``varbinary`` types, if limit is set greater than allowed 255
bytes, the type will be changed to the best matching blob type given the
length::

    <?php

    use Migrations\Db\Adapter\MysqlAdapter;

    //...

    $table = $this->table('cart_items');
    $table->addColumn('user_id', 'integer')
          ->addColumn('product_id', 'integer', ['limit' => MysqlAdapter::INT_BIG])
          ->addColumn('subtype_id', 'integer', ['limit' => MysqlAdapter::INT_SMALL])
          ->addColumn('quantity', 'integer', ['limit' => MysqlAdapter::INT_TINY])
          ->create();

Default values with expressions
-------------------------------

If you need to set a default to an expression, you can use a ``Literal`` to have
the column's default value used without any quoting or escaping. This is helpful
when you want to use a function as a default value::

    use Migrations\BaseMigration;
    use Migrations\Db\Literal;

    class AddSomeColumns extends BaseMigration
    {
        public function change(): void
        {
            $this->table('users')
                  ->addColumn('uniqid', 'uuid', [
                      'default' => Literal::from('uuid_generate_v4()')
                  ])
                  ->create();
        }
    }

.. _creating-a-table::

Creating a Table
----------------

Creating a table is really easy using the Table object. Let's create a table to
store a collection of users::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $users = $this->table('users');
            $users->addColumn('username', 'string', ['limit' => 20])
                  ->addColumn('password', 'string', ['limit' => 40])
                  ->addColumn('password_salt', 'string', ['limit' => 40])
                  ->addColumn('email', 'string', ['limit' => 100])
                  ->addColumn('first_name', 'string', ['limit' => 30])
                  ->addColumn('last_name', 'string', ['limit' => 30])
                  ->addColumn('created', 'datetime')
                  ->addColumn('updated', 'datetime', ['null' => true])
                  ->addIndex(['username', 'email'], ['unique' => true])
                  ->create();
        }
    }

Columns are added using the ``addColumn()`` method. We create a unique index
for both the username and email columns using the ``addIndex()`` method.
Finally calling ``create()`` commits the changes to the database.

.. note::

    Migrations automatically creates an auto-incrementing primary key column called ``id`` for every
    table.

The ``id`` option sets the name of the automatically created identity field,
while the ``primary_key`` option selects the field or fields used for primary
key. ``id`` will always override the ``primary_key`` option unless it's set to
false. If you don't need a primary key set ``id`` to false without specifying
a ``primary_key``, and no primary key will be created.

To specify an alternate primary key, you can specify the ``primary_key`` option
when accessing the Table object. Let's disable the automatic ``id`` column and
create a primary key using two columns instead::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('followers', ['id' => false, 'primary_key' => ['user_id', 'follower_id']]);
            $table->addColumn('user_id', 'integer')
                  ->addColumn('follower_id', 'integer')
                  ->addColumn('created', 'datetime')
                  ->create();
        }
    }

Setting a single ``primary_key`` doesn't enable the ``AUTO_INCREMENT`` option.
To simply change the name of the primary key, we need to override the default ``id`` field name::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function up(): void
        {
            $table = $this->table('followers', ['id' => 'user_id']);
            $table->addColumn('follower_id', 'integer')
                  ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                  ->create();
        }
    }

In addition, the MySQL adapter supports following options:

========== ================ ===========
Option     Platform         Description
========== ================ ===========
comment    MySQL, Postgres  set a text comment on the table
collation  MySQL, SqlServer set the table collation *(defaults to database collation)*
row_format MySQL            set the table row format
engine     MySQL            define table engine *(defaults to ``InnoDB``)*
signed     MySQL            whether the primary key is ``signed``  *(defaults to ``true``)*
limit      MySQL            set the maximum length for the primary key
========== ================ ===========

By default, the primary key is ``signed``.
To set it to be unsigned, pass the ``signed`` option with a ``false``
value, or enable the ``Migrations.unsigned_primary_keys`` and
``Migrations.unsigned_ints`` feature flags (see :ref:`feature-flags`).
Both flags should be used together so that foreign key columns match
the primary keys they reference::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('followers', ['signed' => false]);
            $table->addColumn('follower_id', 'integer')
                  ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                  ->create();
        }
    }

If you need to create a table with a different collation than the database,
use::

    <?php
    use Migrations\BaseMigration;

    class CreateCategoriesTable extends BaseMigration
    {
        public function change(): void
        {
            $table = $this
                ->table('categories', [
                    'collation' => 'latin1_german1_ci'
                ])
                ->addColumn('title', 'string')
                ->create();
        }
    }

Note however this can only be done on table creation : there is currently no way
of adding a column to an existing table with a different collation than the
table or the database. Only ``MySQL`` and ``SqlServer`` supports this
configuration key for the time being.

To view available column types and options, see :ref:`adding-columns` for details.

MySQL ALTER TABLE Options
-------------------------

.. versionadded:: 5.0.0
    ``ALGORITHM`` and ``LOCK`` options were added in 5.0.0.

When modifying tables in MySQL, you can control how the ALTER TABLE operation is
performed using the ``algorithm`` and ``lock`` options. This is useful for performing
zero-downtime schema changes on large tables in production environments.

.. code-block:: php

    <?php

    use Migrations\BaseMigration;

    class AddIndexToLargeTable extends BaseMigration
    {
        public function up(): void
        {
            $table = $this->table('large_table');
            $table->addIndex(['status'], [
                'name' => 'idx_status',
            ]);
            $table->update([
                'algorithm' => 'INPLACE',
                'lock' => 'NONE',
            ]);
        }
    }

Available ``algorithm`` values:

============ ===========
Algorithm    Description
============ ===========
DEFAULT      Let MySQL choose the algorithm (default behavior)
INPLACE      Modify the table in place without copying data (when possible)
COPY         Create a copy of the table with the changes (legacy method)
INSTANT      Only modify metadata, no table rebuild (MySQL 8.0+, limited operations)
============ ===========

Available ``lock`` values:

========= ===========
Lock      Description
========= ===========
DEFAULT   Use minimal locking for the algorithm (default behavior)
NONE      Allow concurrent reads and writes during the operation
SHARED    Allow concurrent reads but block writes
EXCLUSIVE Block all reads and writes during the operation
========= ===========

.. note::

    Not all operations support all algorithm/lock combinations. MySQL will raise
    an error if the requested combination is not possible for the operation.
    The ``INSTANT`` algorithm is only available in MySQL 8.0+ and only for specific
    operations like adding columns at the end of a table.

.. warning::

    Using ``ALGORITHM=INPLACE, LOCK=NONE`` does not guarantee zero-downtime for
    all operations. Some operations may still require a table copy or exclusive lock.
    Always test schema changes on a staging environment first.

Table Partitioning
------------------

Migrations supports table partitioning for MySQL and PostgreSQL. Partitioning helps
manage large tables by splitting them into smaller, more manageable pieces.

.. note::

    Partition columns must be included in the primary key for MySQL. SQLite does
    not support partitioning. MySQL's ``RANGE`` and ``LIST`` types only work with
    integer columns - use ``RANGE COLUMNS`` and ``LIST COLUMNS`` for DATE/STRING columns.

RANGE Partitioning
~~~~~~~~~~~~~~~~~~

RANGE partitioning is useful when you want to partition by numeric ranges. For MySQL,
use ``TYPE_RANGE`` with integer columns or expressions, and ``TYPE_RANGE_COLUMNS`` for
DATE/DATETIME/STRING columns::

    <?php

    use Migrations\BaseMigration;
    use Migrations\Db\Table\Partition;

    class CreatePartitionedOrders extends BaseMigration
    {
        public function change(): void
        {
            // Use RANGE COLUMNS for DATE columns in MySQL
            $table = $this->table('orders', [
                'id' => false,
                'primary_key' => ['id', 'order_date'],
            ]);
            $table->addColumn('id', 'integer', ['identity' => true])
                  ->addColumn('order_date', 'date')
                  ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
                  ->partitionBy(Partition::TYPE_RANGE_COLUMNS, 'order_date')
                  ->addPartition('p2022', '2023-01-01')
                  ->addPartition('p2023', '2024-01-01')
                  ->addPartition('p2024', '2025-01-01')
                  ->addPartition('pmax', 'MAXVALUE')
                  ->create();
        }
    }

LIST Partitioning
~~~~~~~~~~~~~~~~~

LIST partitioning is useful when you want to partition by discrete values. For MySQL,
use ``TYPE_LIST`` with integer columns and ``TYPE_LIST_COLUMNS`` for STRING columns::

    <?php

    use Migrations\BaseMigration;
    use Migrations\Db\Table\Partition;

    class CreatePartitionedCustomers extends BaseMigration
    {
        public function change(): void
        {
            // Use LIST COLUMNS for STRING columns in MySQL
            $table = $this->table('customers', [
                'id' => false,
                'primary_key' => ['id', 'region'],
            ]);
            $table->addColumn('id', 'integer', ['identity' => true])
                  ->addColumn('region', 'string', ['limit' => 20])
                  ->addColumn('name', 'string')
                  ->partitionBy(Partition::TYPE_LIST_COLUMNS, 'region')
                  ->addPartition('p_americas', ['US', 'CA', 'MX', 'BR'])
                  ->addPartition('p_europe', ['UK', 'DE', 'FR', 'IT'])
                  ->addPartition('p_asia', ['JP', 'CN', 'IN', 'KR'])
                  ->create();
        }
    }

HASH Partitioning
~~~~~~~~~~~~~~~~~

HASH partitioning distributes data evenly across a specified number of partitions::

    <?php

    use Migrations\BaseMigration;
    use Migrations\Db\Table\Partition;

    class CreatePartitionedSessions extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('sessions');
            $table->addColumn('user_id', 'integer')
                  ->addColumn('data', 'text')
                  ->partitionBy(Partition::TYPE_HASH, 'user_id', ['count' => 8])
                  ->create();
        }
    }

KEY Partitioning (MySQL only)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

KEY partitioning is similar to HASH but uses MySQL's internal hashing function::

    <?php

    use Migrations\BaseMigration;
    use Migrations\Db\Table\Partition;

    class CreatePartitionedCache extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('cache', [
                'id' => false,
                'primary_key' => ['cache_key'],
            ]);
            $table->addColumn('cache_key', 'string', ['limit' => 255])
                  ->addColumn('value', 'binary')
                  ->partitionBy(Partition::TYPE_KEY, 'cache_key', ['count' => 16])
                  ->create();
        }
    }

Partitioning with Expressions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can partition by expressions using the ``Literal`` class::

    <?php

    use Migrations\BaseMigration;
    use Migrations\Db\Literal;
    use Migrations\Db\Table\Partition;

    class CreatePartitionedEvents extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('events', [
                'id' => false,
                'primary_key' => ['id', 'created_at'],
            ]);
            $table->addColumn('id', 'integer', ['identity' => true])
                  ->addColumn('created_at', 'datetime')
                  ->partitionBy(Partition::TYPE_RANGE, Literal::from('YEAR(created_at)'))
                  ->addPartition('p2022', 2023)
                  ->addPartition('p2023', 2024)
                  ->addPartition('pmax', 'MAXVALUE')
                  ->create();
        }
    }

Modifying Partitions on Existing Tables
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can add or drop partitions on existing partitioned tables::

    <?php

    use Migrations\BaseMigration;

    class ModifyOrdersPartitions extends BaseMigration
    {
        public function up(): void
        {
            // Add a new partition
            $this->table('orders')
                ->addPartitionToExisting('p2025', '2026-01-01')
                ->update();
        }

        public function down(): void
        {
            // Drop the partition
            $this->table('orders')
                ->dropPartition('p2025')
                ->update();
        }
    }

Saving Changes
--------------

When working with the Table object, Migrations stores certain operations in a
pending changes cache. Once you have made the changes you want to the table,
you must save them. To perform this operation, Migrations provides three methods,
``create()``, ``update()``, and ``save()``. ``create()`` will first create
the table and then run the pending changes. ``update()`` will just run the
pending changes, and should be used when the table already exists. ``save()``
is a helper function that checks first if the table exists and if it does not
will run ``create()``, else it will run ``update()``.

As stated above, when using the ``change()`` migration method, you should always
use ``create()`` or ``update()``, and never ``save()`` as otherwise migrating
and rolling back may result in different states, due to ``save()`` calling
``create()`` when running migrate and then ``update()`` on rollback. When
using the ``up()``/``down()`` methods, it is safe to use either ``save()`` or
the more explicit methods.

When in doubt with working with tables, it is always recommended to call
the appropriate function and commit any pending changes to the database.


Renaming a Column
-----------------

To rename a column, access an instance of the Table object then call the
``renameColumn()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table->renameColumn('bio', 'biography')
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $table = $this->table('users');
            $table->renameColumn('biography', 'bio')
                   ->save();
        }
    }

Adding a Column After Another Column
------------------------------------

When adding a column with the MySQL adapter, you can dictate its position using
the ``after`` option, where its value is the name of the column to position it
after::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Change Method.
         */
        public function change(): void
        {
            $table = $this->table('users');
            $table->addColumn('city', 'string', ['after' => 'email'])
                  ->update();
        }
    }

This would create the new column ``city`` and position it after the ``email``
column. The ``\Migrations\Db\Adapter\MysqlAdapter::FIRST`` constant can be used
to specify that the new column should be created as the first column in that
table.

Dropping a Column
-----------------

To drop a column, use the ``removeColumn()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table->removeColumn('short_name')
                  ->save();
        }
    }


Specifying a Column Limit
-------------------------

You can limit the maximum length of a column by using the ``limit`` option::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Change Method.
         */
        public function change(): void
        {
            $table = $this->table('tags');
            $table->addColumn('short_name', 'string', ['limit' => 30])
                  ->update();
        }
    }

Changing Column Attributes
--------------------------

There are two methods for modifying existing columns:

Updating Columns (Recommended)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

To modify specific column attributes while preserving others, use the ``updateColumn()`` method.
This method automatically preserves unspecified attributes like defaults, nullability, limits, etc.::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $users = $this->table('users');
            // Make email nullable, preserving all other attributes
            $users->updateColumn('email', null, ['null' => true])
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $users = $this->table('users');
            $users->updateColumn('email', null, ['null' => false])
                  ->save();
        }
    }

You can pass ``null`` as the column type to preserve the existing type, or specify a new type::

    // Preserve type and other attributes, only change nullability
    $table->updateColumn('email', null, ['null' => true]);

    // Change type to biginteger, preserve default and other attributes
    $table->updateColumn('user_id', 'biginteger');

    // Change default value, preserve everything else
    $table->updateColumn('status', null, ['default' => 'active']);

The following attributes are automatically preserved by ``updateColumn()``:

- Default values
- NULL/NOT NULL constraint
- Column limit/length
- Decimal scale/precision
- Comments
- Signed/unsigned (for numeric types)
- Collation and encoding
- Enum/set values

Changing Columns (Traditional)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

To completely replace a column definition, use the ``changeColumn()`` method.
This method requires you to specify all desired column attributes.
See :ref:`valid-column-types` and `Valid Column Options`_ for allowed values::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $users = $this->table('users');
            // Must specify all attributes
            $users->changeColumn('email', 'string', [
                      'limit' => 255,
                      'null' => true,
                      'default' => null,
                  ])
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

You can enable attribute preservation with ``changeColumn()`` by passing
``'preserveUnspecified' => true`` in the options::

    $table->changeColumn('email', 'string', [
        'null' => true,
        'preserveUnspecified' => true,
    ]);

.. note::

    For most use cases, ``updateColumn()`` is recommended as it is safer and requires
    less code. Use ``changeColumn()`` when you need to completely redefine a column
    or when working with legacy code that expects the traditional behavior.

Working With Indexes
--------------------

To add an index to a table you can simply call the ``addIndex()`` method on the
table object::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table->addColumn('city', 'string')
                  ->addIndex(['city'])
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

By default Migrations instructs the database adapter to create a simple index. We
can pass an additional parameter ``unique`` to the ``addIndex()`` method to
specify a unique index. We can also explicitly specify a name for the index
using the ``name`` parameter, the index columns sort order can also be specified using
the ``order`` parameter. The order parameter takes an array of column names and sort order key/value pairs::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table->addColumn('email', 'string')
                  ->addColumn('username','string')
                  ->addIndex(['email', 'username'], [
                        'unique' => true,
                        'name' => 'idx_users_email',
                        'order' => ['email' => 'DESC', 'username' => 'ASC']]
                  )
                  ->save();
        }
    }

As of 4.6.0, you can use ``BaseMigration::index()`` to get a fluent builder to
define indexes::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table->addColumn('email', 'string')
                  ->addColumn('username','string')
                  ->addIndex(
                      $this->index(['email', 'username'])
                          ->setType('unique')
                          ->setName('idx_users_email')
                          ->setOrder(['email' => 'DESC', 'username' => 'ASC'])
                  )
                  ->save();
        }
    }


The MySQL adapter also supports ``fulltext`` indexes. If you are using a version before 5.6 you must
ensure the table uses the ``MyISAM`` engine::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('users', ['engine' => 'MyISAM']);
            $table->addColumn('email', 'string')
                  ->addIndex('email', ['type' => 'fulltext'])
                  ->create();
        }
    }

MySQL adapter supports setting the index length defined by limit option.
When you are using a multi-column index, you are able to define each column index length.
The single column index can define its index length with or without defining column name in limit option::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('users');
            $table->addColumn('email', 'string')
                  ->addColumn('username','string')
                  ->addColumn('user_guid', 'string', ['limit' => 36])
                  ->addIndex(['email','username'], ['limit' => ['email' => 5, 'username' => 2]])
                  ->addIndex('user_guid', ['limit' => 6])
                  ->create();
        }
    }

The SQL Server and PostgreSQL adapters support ``include`` (non-key) columns on indexes::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('users');
            $table->addColumn('email', 'string')
                  ->addColumn('firstname','string')
                  ->addColumn('lastname','string')
                  ->addIndex(['email'], ['include' => ['firstname', 'lastname']])
                  ->create();
        }
    }

PostgreSQL, SQLServer, and SQLite support partial indexes by defining where
clauses for the index::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('users');
            $table->addColumn('email', 'string')
                  ->addColumn('is_verified','boolean')
                  ->addIndex(
                      $this->index('email')
                          ->setName('user_email_verified_idx')
                          ->setType('unique')
                          ->setWhere('is_verified = true')
                  )
                  ->create();
        }
    }

PostgreSQL can create indexes concurrently which avoids taking disruptive locks
during index creation::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('users');
            $table->addColumn('email', 'string')
                  ->addIndex(
                      $this->index('email')
                          ->setName('user_email_unique_idx')
                          ->setType('unique')
                          ->setConcurrently(true)
                  )
                  ->create();
        }
    }

PostgreSQL adapters also supports Generalized Inverted Index ``gin`` indexes::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function change(): void
        {
            $table = $this->table('users');
            $table->addColumn('address', 'string')
                  ->addIndex('address', ['type' => 'gin'])
                  ->create();
        }
    }

Removing indexes is as easy as calling the ``removeIndex()`` method. You must
call this method for each index::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table->removeIndex(['email'])
                ->save();

            // alternatively, you can delete an index by its name, ie:
            $table->removeIndexByName('idx_users_email')
                ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

.. versionadded:: 4.6.0
    ``Index::setWhere()``, and ``Index::setConcurrently()`` were added.


Working With Foreign Keys
-------------------------

Migrations has support for creating foreign key constraints on your database tables.
Let's add a foreign key to an example table::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('tags');
            $table->addColumn('tag_name', 'string')
                  ->save();

            $refTable = $this->table('tag_relationships');
            $refTable->addColumn('tag_id', 'integer', ['null' => true])
                    ->addForeignKey(
                        'tag_id',
                        'tags',
                        'id',
                        ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'],
                    )
                    ->save();

        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

The 'delete' and 'update' options allow you to define the ``ON UPDATE`` and ``ON
DELETE`` behavior. Possibles values are 'SET_NULL', 'NO_ACTION', 'CASCADE' and
'RESTRICT'.  If 'SET_NULL' is used then the column must be created as nullable
with the option ``['null' => true]``.

Foreign keys can be defined with arrays of columns to build constraints between
tables with composite keys::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        public function up(): void
        {
            $table = $this->table('follower_events');
            $table->addColumn('user_id', 'integer')
                ->addColumn('follower_id', 'integer')
                ->addColumn('event_id', 'integer')
                ->addForeignKey(
                    ['user_id', 'follower_id'],
                    'followers',
                    ['user_id', 'follower_id'],
                    [
                        'delete'=> 'NO_ACTION',
                        'update'=> 'NO_ACTION',
                        'constraint' => 'user_follower_id',
                    ]
                )
                ->save();
        }
    }

The options parameter of ``addForeignKey()`` supports the following options:

========== ===========
Option     Description
========== ===========
update     set an action to be triggered when the row is updated
delete     set an action to be triggered when the row is deleted
constraint set a name to be used by foreign key constraint
deferrable define deferred constraint application (postgres only)
========== ===========

Using the ``foreignKey()`` method provides a fluent builder to define a foreign
key::

    <?php

    use Migrations\BaseMigration;
    use Migrations\Db\Table\ForeignKey;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('articles');
            $table->addForeignKey(
                $this->foreignKey()
                    ->setColumns('user_id')
                    ->setReferencedTable('users')
                    ->setReferencedColumns('user_id')
                    ->setDelete(ForeignKey::CASCADE)
                    ->setName('article_user_fk')
            )
            ->save();
        }
    }

.. versionadded:: 4.6.0
   The ``foreignKey`` method was added.

We can also easily check if a foreign key exists::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('tag_relationships');
            $exists = $table->hasForeignKey('tag_id');
            if ($exists) {
                // do something
            }
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Finally, to delete a foreign key, use the ``dropForeignKey`` method.

Note that like other methods in the ``Table`` class, ``dropForeignKey`` also
needs ``save()`` to be called at the end in order to be executed. This allows
Migrations to intelligently plan migrations when more than one table is
involved::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('tag_relationships');
            $table->dropForeignKey('tag_id')->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Working With Check Constraints
-------------------------------

.. versionadded:: 5.0.0
    Check constraints were added in 5.0.0.

Check constraints allow you to enforce data validation rules at the database level.
They are particularly useful for ensuring data integrity across your application.

.. note::

    Check constraints are supported by MySQL 8.0.16+, PostgreSQL, and SQLite.
    SQL Server support is planned for a future release.

Adding a Check Constraint
~~~~~~~~~~~~~~~~~~~~~~~~~~

You can add a check constraint to a table using the ``addCheckConstraint()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('products');
            $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
                  ->addCheckConstraint('price_positive', 'price > 0')
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $table = $this->table('products');
            $table->dropCheckConstraint('price_positive')
                  ->save();
        }
    }

The first argument is the constraint name, and the second is the SQL expression
that defines the constraint. The expression should evaluate to a boolean value.

Using the CheckConstraint Fluent Builder
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

For more complex scenarios, you can use the ``checkConstraint()`` method to get
a fluent builder::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table->addColumn('age', 'integer')
                  ->addColumn('status', 'string', ['limit' => 20])
                  ->addCheckConstraint(
                      $this->checkConstraint()
                          ->setName('age_valid')
                          ->setExpression('age >= 18 AND age <= 120')
                  )
                  ->addCheckConstraint(
                      $this->checkConstraint()
                          ->setName('status_valid')
                          ->setExpression("status IN ('active', 'inactive', 'pending')")
                  )
                  ->save();
        }
    }

Auto-Generated Constraint Names
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If you don't specify a constraint name, one will be automatically generated based
on the table name and expression hash::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('inventory');
            $table->addColumn('quantity', 'integer')
                  // Name will be auto-generated like 'inventory_chk_a1b2c3d4'
                  ->addCheckConstraint(
                      $this->checkConstraint()
                          ->setExpression('quantity >= 0')
                  )
                  ->save();
        }
    }

Complex Check Constraints
~~~~~~~~~~~~~~~~~~~~~~~~~~

Check constraints can reference multiple columns and use complex SQL expressions::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('date_ranges');
            $table->addColumn('start_date', 'date')
                  ->addColumn('end_date', 'date')
                  ->addColumn('discount', 'decimal', ['precision' => 5, 'scale' => 2])
                  ->addCheckConstraint('valid_date_range', 'end_date >= start_date')
                  ->addCheckConstraint('valid_discount', 'discount BETWEEN 0 AND 100')
                  ->save();
        }
    }

Checking if a Check Constraint Exists
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can verify if a check constraint exists using the ``hasCheckConstraint()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('products');
            $exists = $table->hasCheckConstraint('price_positive');
            if ($exists) {
                // do something
            } else {
                $table->addCheckConstraint('price_positive', 'price > 0')
                      ->save();
            }
        }
    }

Dropping a Check Constraint
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

To remove a check constraint, use the ``dropCheckConstraint()`` method with the
constraint name::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('products');
            $table->dropCheckConstraint('price_positive')
                  ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $table = $this->table('products');
            $table->addCheckConstraint('price_positive', 'price > 0')
                  ->save();
        }
    }

.. note::

    Like other table operations, ``dropCheckConstraint()`` requires ``save()``
    to be called to execute the change.

Database-Specific Behavior
~~~~~~~~~~~~~~~~~~~~~~~~~~~

**MySQL (8.0.16+)**

Check constraints are fully supported. MySQL stores constraint metadata in the
``INFORMATION_SCHEMA.CHECK_CONSTRAINTS`` table.

**PostgreSQL**

Check constraints are fully supported and stored in the ``pg_constraint`` catalog.
PostgreSQL allows the most flexible expressions in check constraints.

**SQLite**

Check constraints are supported but with some limitations. SQLite does not support
``ALTER TABLE`` operations for check constraints, so adding or dropping constraints
requires recreating the entire table. This is handled automatically by the adapter.

**SQL Server**

Check constraint support for SQL Server is planned for a future release.

Determining Whether a Table Exists
----------------------------------

You can determine whether or not a table exists by using the ``hasTable()``
method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $exists = $this->hasTable('users');
            if ($exists) {
                // do something
            }
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Dropping a Table
----------------

Tables can be dropped quite easily using the ``drop()`` method. It is a
good idea to recreate the table again in the ``down()`` method.

Note that like other methods in the ``Table`` class, ``drop`` also needs ``save()``
to be called at the end in order to be executed. This allows Migrations to intelligently
plan migrations when more than one table is involved::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $this->table('users')->drop()->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $users = $this->table('users');
            $users->addColumn('username', 'string', ['limit' => 20])
                  ->addColumn('password', 'string', ['limit' => 40])
                  ->addColumn('password_salt', 'string', ['limit' => 40])
                  ->addColumn('email', 'string', ['limit' => 100])
                  ->addColumn('first_name', 'string', ['limit' => 30])
                  ->addColumn('last_name', 'string', ['limit' => 30])
                  ->addColumn('created', 'datetime')
                  ->addColumn('updated', 'datetime', ['null' => true])
                  ->addIndex(['username', 'email'], ['unique' => true])
                  ->save();
        }
    }

Renaming a Table
----------------

To rename a table access an instance of the Table object then call the
``rename()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table
                ->rename('legacy_users')
                ->update();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $table = $this->table('legacy_users');
            $table
                ->rename('users')
                ->update();
        }
    }

Changing the Primary Key
------------------------

To change the primary key on an existing table, use the ``changePrimaryKey()``
method. Pass in a column name or array of columns names to include in the
primary key, or ``null`` to drop the primary key. Note that the mentioned
columns must be added to the table, they will not be added implicitly::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $users = $this->table('users');
            $users
                ->addColumn('username', 'string', ['limit' => 20, 'null' => false])
                ->addColumn('password', 'string', ['limit' => 40])
                ->save();

            $users
                ->addColumn('new_id', 'integer', ['null' => false])
                ->changePrimaryKey(['new_id', 'username'])
                ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Creating Custom Primary Keys
----------------------------

You can specify a ``autoId`` property in the Migration class and set it to
``false``, which will turn off the automatic ``id`` column creation. You will
need to manually create the column that will be used as a primary key and add
it to the table declaration::

    <?php
    use Migrations\BaseMigration;

    class CreateProductsTable extends BaseMigration
    {

        public bool $autoId = false;

        public function up(): void
        {
            $table = $this->table('products');
            $table
                ->addColumn('id', 'uuid')
                ->addPrimaryKey('id')
                ->addColumn('name', 'string')
                ->addColumn('description', 'text')
                ->create();
        }
    }

The above will create a ``CHAR(36)`` ``id`` column that is also the primary key.

When specifying a custom primary key on the command line, you must note
it as the primary key in the id field, otherwise you may get an error
regarding duplicate id fields, i.e.:

.. code-block:: bash

    bin/cake bake migration CreateProducts id:uuid:primary name:string description:text created modified


All baked migrations and snapshot will use this new way when necessary.

.. warning::

    Dealing with primary key can only be done on table creation operations.
    This is due to limitations for some database servers the plugin supports.

Changing the Table Comment
--------------------------

To change the comment on an existing table, use the ``changeComment()`` method.
Pass in a string to set as the new table comment, or ``null`` to drop the existing comment::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $users = $this->table('users');
            $users
                ->addColumn('username', 'string', ['limit' => 20])
                ->addColumn('password', 'string', ['limit' => 40])
                ->save();

            $users
                ->changeComment('This is the table with users auth information, password should be encrypted')
                ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Checking Columns
================

``BaseMigration`` also provides methods for introspecting the current schema,
allowing you to conditionally make changes to schema, or read data.
Schema is inspected **when the migration is run**.

Get a column list
-----------------

To retrieve all table columns, simply create a ``table`` object and call
``getColumns()`` method. This method will return an array of Column classes with
basic info. Example below::

    <?php

    use Migrations\BaseMigration;

    class ColumnListMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $columns = $this->table('users')->getColumns();
            ...
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            ...
        }
    }

Get a column by name
--------------------

To retrieve one table column, simply create a ``table`` object and call the
``getColumn()`` method. This method will return a Column class with basic info
or NULL when the column doesn't exist. Example below::

    <?php

    use Migrations\BaseMigration;

    class ColumnListMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $column = $this->table('users')->getColumn('email');
            ...
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            ...
        }
    }

Checking whether a column exists
--------------------------------

You can check if a table already has a certain column by using the
``hasColumn()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Change Method.
         */
        public function change(): void
        {
            $table = $this->table('user');
            $column = $table->hasColumn('username');

            if ($column) {
                // do something
            }

        }
    }


        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Dropping a Table
----------------

Tables can be dropped quite easily using the ``drop()`` method. It is a
good idea to recreate the table again in the ``down()`` method.

Note that like other methods in the ``Table`` class, ``drop`` also needs ``save()``
to be called at the end in order to be executed. This allows Migrations to intelligently
plan migrations when more than one table is involved::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $this->table('users')->drop()->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $users = $this->table('users');
            $users->addColumn('username', 'string', ['limit' => 20])
                  ->addColumn('password', 'string', ['limit' => 40])
                  ->addColumn('password_salt', 'string', ['limit' => 40])
                  ->addColumn('email', 'string', ['limit' => 100])
                  ->addColumn('first_name', 'string', ['limit' => 30])
                  ->addColumn('last_name', 'string', ['limit' => 30])
                  ->addColumn('created', 'datetime')
                  ->addColumn('updated', 'datetime', ['null' => true])
                  ->addIndex(['username', 'email'], ['unique' => true])
                  ->save();
        }
    }

Renaming a Table
----------------

To rename a table access an instance of the Table object then call the
``rename()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $table = $this->table('users');
            $table
                ->rename('legacy_users')
                ->update();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            $table = $this->table('legacy_users');
            $table
                ->rename('users')
                ->update();
        }
    }

Changing the Primary Key
------------------------

To change the primary key on an existing table, use the ``changePrimaryKey()``
method. Pass in a column name or array of columns names to include in the
primary key, or ``null`` to drop the primary key. Note that the mentioned
columns must be added to the table, they will not be added implicitly::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $users = $this->table('users');
            $users
                ->addColumn('username', 'string', ['limit' => 20, 'null' => false])
                ->addColumn('password', 'string', ['limit' => 40])
                ->save();

            $users
                ->addColumn('new_id', 'integer', ['null' => false])
                ->changePrimaryKey(['new_id', 'username'])
                ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Creating Custom Primary Keys
----------------------------

You can specify a ``autoId`` property in the Migration class and set it to
``false``, which will turn off the automatic ``id`` column creation. You will
need to manually create the column that will be used as a primary key and add
it to the table declaration::

    <?php
    use Migrations\BaseMigration;

    class CreateProductsTable extends BaseMigration
    {

        public bool $autoId = false;

        public function up(): void
        {
            $table = $this->table('products');
            $table
                ->addColumn('id', 'uuid')
                ->addPrimaryKey('id')
                ->addColumn('name', 'string')
                ->addColumn('description', 'text')
                ->create();
        }
    }

The above will create a ``CHAR(36)`` ``id`` column that is also the primary key.

When specifying a custom primary key on the command line, you must note
it as the primary key in the id field, otherwise you may get an error
regarding duplicate id fields, i.e.:

.. code-block:: bash

    bin/cake bake migration CreateProducts id:uuid:primary name:string description:text created modified


All baked migrations and snapshot will use this new way when necessary.

.. warning::

    Dealing with primary key can only be done on table creation operations.
    This is due to limitations for some database servers the plugin supports.

Changing the Table Comment
--------------------------

To change the comment on an existing table, use the ``changeComment()`` method.
Pass in a string to set as the new table comment, or ``null`` to drop the existing comment::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $users = $this->table('users');
            $users
                ->addColumn('username', 'string', ['limit' => 20])
                ->addColumn('password', 'string', ['limit' => 40])
                ->save();

            $users
                ->changeComment('This is the table with users auth information, password should be encrypted')
                ->save();
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {

        }
    }

Checking Columns
================

``BaseMigration`` also provides methods for introspecting the current schema,
allowing you to conditionally make changes to schema, or read data.
Schema is inspected **when the migration is run**.

Get a column list
-----------------

To retrieve all table columns, simply create a ``table`` object and call
``getColumns()`` method. This method will return an array of Column classes with
basic info. Example below::

    <?php

    use Migrations\BaseMigration;

    class ColumnListMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $columns = $this->table('users')->getColumns();
            ...
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            ...
        }
    }

Get a column by name
--------------------

To retrieve one table column, simply create a ``table`` object and call the
``getColumn()`` method. This method will return a Column class with basic info
or NULL when the column doesn't exist. Example below::

    <?php

    use Migrations\BaseMigration;

    class ColumnListMigration extends BaseMigration
    {
        /**
         * Migrate Up.
         */
        public function up(): void
        {
            $column = $this->table('users')->getColumn('email');
            ...
        }

        /**
         * Migrate Down.
         */
        public function down(): void
        {
            ...
        }
    }

Checking whether a column exists
--------------------------------

You can check if a table already has a certain column by using the
``hasColumn()`` method::

    <?php

    use Migrations\BaseMigration;

    class MyNewMigration extends BaseMigration
    {
        /**
         * Change Method.
         */
        public function change(): void
        {
            $table = $this->table('user');
            $column = $table->hasColumn('username');

            if ($column) {
                // do something
            }

        }
    }


Changing templates
------------------

See :ref:`custom-seed-migration-templates` for how to customize the templates
used to generate migrations.

Database-Specific Limitations
=============================

While Migrations aims to provide a database-agnostic API, some features have
database-specific limitations or are not available on all platforms.

SQL Server
----------

The following features are not supported on SQL Server:

**Check Constraints**

Check constraints are not currently implemented for SQL Server. Attempting to
use ``addCheckConstraint()`` or ``dropCheckConstraint()`` will throw a
``BadMethodCallException``.

**Table Comments**

SQL Server does not support table comments. Attempting to use ``changeComment()``
will throw a ``BadMethodCallException``.

**INSERT IGNORE / insertOrSkip()**

SQL Server does not support the ``INSERT IGNORE`` syntax used by ``insertOrSkip()``.
This method will throw a ``RuntimeException`` on SQL Server. Use ``insertOrUpdate()``
instead for upsert operations, which uses ``MERGE`` statements on SQL Server.

SQLite
------

**Foreign Key Names**

SQLite does not support named foreign keys. The foreign key constraint name option
is ignored when creating foreign keys on SQLite.

**Table Comments**

SQLite does not support table comments directly. Comments are stored as metadata
but not in the database itself.

**Check Constraint Modifications**

SQLite does not support ``ALTER TABLE`` operations for check constraints. Adding or
dropping check constraints requires recreating the entire table, which is handled
automatically by the adapter.

**Table Partitioning**

SQLite does not support table partitioning.

PostgreSQL
----------

**KEY Partitioning**

PostgreSQL does not support MySQL's ``KEY`` partitioning type. Use ``HASH``
partitioning instead for similar distribution behavior.

MySQL/MariaDB
-------------

**insertOrUpdate() Conflict Columns**

For MySQL, the ``$conflictColumns`` parameter in ``insertOrUpdate()`` is ignored
because MySQL's ``ON DUPLICATE KEY UPDATE`` automatically applies to all unique
constraints. PostgreSQL and SQLite require this parameter to be specified.

**MariaDB GIS/Geometry**

Some geometry column features may not work correctly on MariaDB due to differences
in GIS implementation compared to MySQL.
