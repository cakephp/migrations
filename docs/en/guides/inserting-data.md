# Inserting Data

Migrations makes it easy to insert data into your tables. Whilst this feature
is intended for the [seed feature](seeding), you are also free to use the
insert methods in your migrations:

```php
<?php

use Migrations\BaseMigration;

class NewStatus extends BaseMigration
{
    /**
     * Migrate Up.
     */
    public function up(): void
    {
        $table = $this->table('status');

        // inserting only one row
        $singleRow = [
            'id' => 1,
            'name' => 'In Progress',
        ];

        $table->insert($singleRow)->saveData();

        // inserting multiple rows
        $rows = [
            [
                'id' => 2,
                'name' => 'Stopped',
            ],
            [
                'id' => 3,
                'name' => 'Queued',
            ],
        ];

        $table->insert($rows)->saveData();
    }

    /**
     * Migrate Down.
     */
    public function down(): void
    {
        $this->execute('DELETE FROM status');
    }
}
```

> [!NOTE]
> You cannot use insert methods inside `change()`. Use `up()` and `down()`
> instead.
