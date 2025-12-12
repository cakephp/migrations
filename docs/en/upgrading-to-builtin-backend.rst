Upgrading to the builtin backend
################################

As of migrations 4.3 there is a new migrations backend that uses CakePHP's
database abstractions and ORM. In 4.4, the ``builtin`` backend became the
default backend. As of migrations 5.0, phinx has been removed as a dependency
and only the builtin backend is supported. This greatly reduces the dependency
footprint of migrations.

What is the same?
=================

Your migrations shouldn't have to change much to adapt to the new backend.
The builtin backend provides similar functionality to what was available with
phinx. If your migrations don't work in a way that could be addressed by the
changes outlined below, please open an issue.

What is different?
==================

Command Structure Changes
-------------------------

As of migrations 5.0, the command structure has changed. The old phinx wrapper
commands have been removed and replaced with new command names:

**Seeds:**

.. code-block:: bash

    # Old (4.x and earlier)
    bin/cake migrations seed
    bin/cake migrations seed --seed Articles

    # New (5.x and later)
    bin/cake seeds run
    bin/cake seeds run Articles

The new commands are:

- ``bin/cake seeds run`` - Run seed classes
- ``bin/cake seeds status`` - Show seed execution status
- ``bin/cake seeds reset`` - Reset seed execution tracking
- ``bin/cake bake seed`` - Generate new seed classes

Maintaining Backward Compatibility
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If you need to maintain the old command structure for existing scripts or CI/CD
pipelines, you can add command aliases in your application. In your
``src/Application.php`` file, add the following to the ``console()`` method:

.. code-block:: php

    public function console(CommandCollection $commands): CommandCollection
    {
        // Add your application's commands
        $commands = $this->addConsoleCommands($commands);

        // Add backward compatibility aliases for migrations 4.x commands
        $commands->add('migrations seed', \Migrations\Command\SeedCommand::class);

        return $commands;
    }

For multiple aliases, you can add them all together:

.. code-block:: php

    // Add multiple backward compatibility aliases
    $commands->add('migrations seed', \Migrations\Command\SeedCommand::class);
    $commands->add('migrations seed:run', \Migrations\Command\SeedCommand::class);
    $commands->add('migrations seed:status', \Migrations\Command\SeedStatusCommand::class);

This allows gradual migration of scripts and documentation without modifying the
migrations plugin or creating wrapper command classes.

API Changes
-----------

If your migrations are using the ``AdapterInterface`` to fetch rows or update
rows you will need to update your code. If you use ``Adapter::query()`` to
execute queries, the return of this method is now
``Cake\Database\StatementInterface`` instead. This impacts ``fetchAll()``,
and ``fetch()``::

    // This
    $stmt = $this->getAdapter()->query('SELECT * FROM articles');
    $rows = $stmt->fetchAll();

    // Now needs to be
    $stmt = $this->getAdapter()->query('SELECT * FROM articles');
    $rows = $stmt->fetchAll('assoc');

Similar changes are for fetching a single row::

    // This
    $stmt = $this->getAdapter()->query('SELECT * FROM articles');
    $rows = $stmt->fetch();

    // Now needs to be
    $stmt = $this->getAdapter()->query('SELECT * FROM articles');
    $rows = $stmt->fetch('assoc');

Unified Migrations Table
========================

As of migrations 5.x, there is a new unified ``cake_migrations`` table that
replaces the legacy ``phinxlog`` tables. This provides several benefits:

- **Single table for all migrations**: Instead of separate ``phinxlog`` (app)
  and ``{plugin}_phinxlog`` (plugins) tables, all migrations are tracked in
  one ``cake_migrations`` table with a ``plugin`` column.
- **Simpler database schema**: Fewer migration tracking tables to manage.
- **Better plugin support**: Plugin migrations are properly namespaced.

Backward Compatibility
----------------------

For existing applications with ``phinxlog`` tables:

- **Automatic detection**: If any ``phinxlog`` table exists, migrations will
  continue using the legacy tables automatically.
- **No forced migration**: Existing applications don't need to change anything.
- **Opt-in upgrade**: You can migrate to the new table when you're ready.

Configuration
-------------

The ``Migrations.legacyTables`` configuration option controls the behavior:

.. code-block:: php

    // config/app.php or config/app_local.php
    'Migrations' => [
        // null (default): Autodetect - use legacy if phinxlog tables exist
        // false: Force use of new cake_migrations table
        // true: Force use of legacy phinxlog tables
        'legacyTables' => null,
    ],

Upgrading to the Unified Table
------------------------------

To migrate from ``phinxlog`` tables to the new ``cake_migrations`` table:

1. **Preview the upgrade** (dry run):

   .. code-block:: bash

       bin/cake migrations upgrade --dry-run

2. **Run the upgrade**:

   .. code-block:: bash

       bin/cake migrations upgrade

3. **Update your configuration**:

   .. code-block:: php

       // config/app.php
       'Migrations' => [
           'legacyTables' => false,
       ],

4. **Optionally drop phinx tables**: Your migration history is preserved
   by default. Use ``--drop-tables`` to drop the ``phinxlog``tables after
   verifying your migrations run correctly.

   .. code-block:: bash

       bin/cake migrations upgrade --drop-tables

Rolling Back
------------

If you need to revert to phinx tables after upgrading:

1. Set ``'legacyTables' => true`` in your configuration.

.. warning::

    You cannot rollback after running ``upgrade --drop-tables``.


New Installations
-----------------

For new applications without any existing ``phinxlog`` tables, the unified
``cake_migrations`` table is used automatically. No configuration is needed.

Problems with the builtin backend?
==================================

If your migrations contain errors when run with the builtin backend, please
open `an issue <https://github.com/cakephp/migrations/issues/new>`_.
