# Executing Queries

Queries can be executed with the `execute()` and `query()` methods. The
`execute()` method returns the number of affected rows whereas the
`query()` method returns the result as a
[CakePHP Statement](https://book.cakephp.org/5/en/orm/database-basics.html#interacting-with-statements). Both methods
accept an optional second parameter `$params` which is an array of elements,
and if used will cause the underlying connection to use a prepared statement:

```php
<?php

use Migrations\BaseMigration;

class MyNewMigration extends BaseMigration
{
    /**
     * Migrate Up.
     */
    public function up(): void
    {
        // execute()
        $count = $this->execute('DELETE FROM users'); // returns the number of affected rows

        // query()
        $stmt = $this->query('SELECT * FROM users'); // returns PDOStatement
        $rows = $stmt->fetchAll(); // returns the result as an array

        // using prepared queries
        $count = $this->execute('DELETE FROM users WHERE id = ?', [5]);
        $stmt = $this->query('SELECT * FROM users WHERE id > ?', [5]); // returns PDOStatement
        $rows = $stmt->fetchAll();
    }

    /**
     * Migrate Down.
     */
    public function down(): void
    {

    }
}
```

## Fetching Rows

There are two methods available to fetch rows. The `fetchRow()` method will
fetch a single row, whilst the `fetchAll()` method will return multiple rows.
Both methods accept raw SQL as their only parameter:

```php
<?php

use Migrations\BaseMigration;

class MyNewMigration extends BaseMigration
{
    /**
     * Migrate Up.
     */
    public function up(): void
    {
        // fetch a user
        $row = $this->fetchRow('SELECT * FROM users');

        // fetch an array of messages
        $rows = $this->fetchAll('SELECT * FROM messages');
    }

    /**
     * Migrate Down.
     */
    public function down(): void
    {

    }
}
```
