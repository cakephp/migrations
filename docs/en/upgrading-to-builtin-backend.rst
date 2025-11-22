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

Problems with the builtin backend?
==================================

If your migrations contain errors when run with the builtin backend, please
open `an issue <https://github.com/cakephp/migrations/issues/new>`_.
