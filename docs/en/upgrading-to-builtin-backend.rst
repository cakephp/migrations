Upgrading to the builtin backend
################################

As of migrations 4.3 there is a new migrations backend that uses CakePHP's
database abstractions and ORM. Longer term this will allow for phinx to be
removed as a dependency. This greatly reduces the dependency footprint of
migrations.

What is the same?
=================

Your migrations shouldn't have to change much to adapt to the new backend.
The migrations backend implements all of the phinx interfaces and can run
migrations based on phinx classes. If your migrations don't work in a way that
could be addressed by the changes outlined below, please open an issue, as we'd
like to maintain as much compatibility as we can.

What is different?
==================

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

Enabling the new backend
========================

The new backend can be enabled through application configuration. Add the
following to your ``config/app.php``::

    return [
        // Other configuration.
        'Migrations' => ['backend' => 'builtin'],
    ];

If your migrations have problems running with the builtin backend, removing this
configuration option will revert to using phinx.
