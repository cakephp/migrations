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
