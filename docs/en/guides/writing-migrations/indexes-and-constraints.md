# Indexes and Constraints

## Working With Indexes

To add an index to a table, call `addIndex()` on the Table object:

```php
<?php
use Migrations\BaseMigration;

class MyNewMigration extends BaseMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('city', 'string')
            ->addIndex(['city'])
            ->save();
    }
}
```

By default, Migrations creates a simple index. You can pass `unique` to create
a unique index, specify a `name`, and control `order` (an array of column
names and sort order key/value pairs):

```php
$this->table('users')
    ->addColumn('email', 'string')
    ->addColumn('username', 'string')
    ->addIndex(['email', 'username'], [
        'unique' => true,
        'name' => 'idx_users_email',
        'order' => ['email' => 'DESC', 'username' => 'ASC'],
    ])
    ->save();
```

As of 4.6.0, you can use `BaseMigration::index()` to get a fluent builder:

```php
$this->table('users')
    ->addIndex(
        $this->index(['email', 'username'])
            ->setType('unique')
            ->setName('idx_users_email')
            ->setOrder(['email' => 'DESC', 'username' => 'ASC'])
    )
    ->save();
```

### MySQL Fulltext Indexes

The MySQL adapter supports `fulltext` indexes. If you are using a version
before 5.6 the table must use the `MyISAM` engine:

```php
$table = $this->table('users', ['engine' => 'MyISAM']);
$table->addColumn('email', 'string')
      ->addIndex('email', ['type' => 'fulltext'])
      ->create();
```

### MySQL Index Length

The MySQL adapter supports setting the index length via the `limit` option.
For multi-column indexes you can define the length per column:

```php
$this->table('users')
    ->addColumn('email', 'string')
    ->addColumn('username', 'string')
    ->addColumn('user_guid', 'string', ['limit' => 36])
    ->addIndex(['email', 'username'], ['limit' => ['email' => 5, 'username' => 2]])
    ->addIndex('user_guid', ['limit' => 6])
    ->create();
```

### Include Columns (SQL Server and PostgreSQL)

The SQL Server and PostgreSQL adapters support `include` (non-key) columns on
indexes:

```php
$this->table('users')
    ->addColumn('email', 'string')
    ->addColumn('firstname', 'string')
    ->addColumn('lastname', 'string')
    ->addIndex(['email'], ['include' => ['firstname', 'lastname']])
    ->create();
```

### Partial Indexes

PostgreSQL, SQL Server, and SQLite support partial indexes via a `WHERE`
clause:

```php
$this->table('users')
    ->addColumn('email', 'string')
    ->addColumn('is_verified', 'boolean')
    ->addIndex(
        $this->index('email')
            ->setName('user_email_verified_idx')
            ->setType('unique')
            ->setWhere('is_verified = true')
    )
    ->create();
```

### Concurrent Index Creation (PostgreSQL)

PostgreSQL can create indexes concurrently, which avoids taking disruptive
locks during index creation:

```php
$this->table('users')
    ->addColumn('email', 'string')
    ->addIndex(
        $this->index('email')
            ->setName('user_email_unique_idx')
            ->setType('unique')
            ->setConcurrently(true)
    )
    ->create();
```

### GIN Indexes (PostgreSQL)

The PostgreSQL adapter also supports Generalized Inverted Index (`gin`)
indexes:

```php
$this->table('users')
    ->addColumn('address', 'string')
    ->addIndex('address', ['type' => 'gin'])
    ->create();
```

### Removing Indexes

Use `removeIndex()` with the list of columns, or `removeIndexByName()` if you
named the index:

```php
$table = $this->table('users');
$table->removeIndex(['email'])
    ->save();

$table->removeIndexByName('idx_users_email')
    ->save();
```

::: info Added in version 4.6.0
`Index::setWhere()` and `Index::setConcurrently()` were added.
:::

## Working With Foreign Keys

Migrations supports creating foreign key constraints on database tables:

```php
<?php
use Migrations\BaseMigration;

class MyNewMigration extends BaseMigration
{
    public function up(): void
    {
        $this->table('tags')
            ->addColumn('tag_name', 'string')
            ->save();

        $this->table('tag_relationships')
            ->addColumn('tag_id', 'integer', ['null' => true])
            ->addForeignKey(
                'tag_id',
                'tags',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'],
            )
            ->save();
    }
}
```

The `delete` and `update` options define the `ON DELETE` and `ON UPDATE`
behavior. Valid values are `SET_NULL`, `NO_ACTION`, `CASCADE`, and
`RESTRICT`. If `SET_NULL` is used, the column must be created as nullable
with `['null' => true]`.

### Composite Foreign Keys

Foreign keys can be defined with arrays of columns to build constraints
between tables with composite keys:

```php
$this->table('follower_events')
    ->addColumn('user_id', 'integer')
    ->addColumn('follower_id', 'integer')
    ->addColumn('event_id', 'integer')
    ->addForeignKey(
        ['user_id', 'follower_id'],
        'followers',
        ['user_id', 'follower_id'],
        [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'user_follower_id',
        ],
    )
    ->save();
```

The options parameter of `addForeignKey()` supports the following:

| Option     | Description                                            |
|------------|--------------------------------------------------------|
| update     | set an action to be triggered when the row is updated  |
| delete     | set an action to be triggered when the row is deleted  |
| constraint | set a name to be used by foreign key constraint        |
| deferrable | define deferred constraint application (postgres only) |

### Fluent Foreign Key Builder

`foreignKey()` returns a fluent builder for more complex cases:

```php
<?php
use Migrations\BaseMigration;
use Migrations\Db\Table\ForeignKey;

class MyNewMigration extends BaseMigration
{
    public function up(): void
    {
        $this->table('articles')
            ->addForeignKey(
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
```

::: info Added in version 4.6.0
The `foreignKey` method was added.
:::

### Checking and Dropping Foreign Keys

Use `hasForeignKey()` to check whether a foreign key exists:

```php
$table = $this->table('tag_relationships');
if ($table->hasForeignKey('tag_id')) {
    // do something
}
```

To delete a foreign key, use `dropForeignKey()`. Like other Table methods, it
needs `save()` to be called at the end:

```php
$this->table('tag_relationships')
    ->dropForeignKey('tag_id')
    ->save();
```

## Working With Check Constraints

::: info Added in version 5.0.0
Check constraints were added in 5.0.0.
:::

Check constraints allow you to enforce data validation rules at the database
level.

> [!NOTE]
> Check constraints are supported by MySQL 8.0.16+, PostgreSQL, and SQLite.
> SQL Server support is planned for a future release.

### Adding a Check Constraint

The first argument is the constraint name, and the second is the SQL
expression that defines the constraint:

```php
<?php
use Migrations\BaseMigration;

class MyNewMigration extends BaseMigration
{
    public function up(): void
    {
        $this->table('products')
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addCheckConstraint('price_positive', 'price > 0')
            ->save();
    }

    public function down(): void
    {
        $this->table('products')
            ->dropCheckConstraint('price_positive')
            ->save();
    }
}
```

### Using the CheckConstraint Fluent Builder

For more complex scenarios, `checkConstraint()` returns a fluent builder:

```php
$this->table('users')
    ->addColumn('age', 'integer')
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
```

### Auto-Generated Constraint Names

If you do not specify a constraint name, one will be automatically generated
based on the table name and expression hash:

```php
$this->table('inventory')
    ->addColumn('quantity', 'integer')
    // Name will be auto-generated like 'inventory_chk_a1b2c3d4'
    ->addCheckConstraint(
        $this->checkConstraint()
            ->setExpression('quantity >= 0')
    )
    ->save();
```

### Complex Check Constraints

Check constraints can reference multiple columns and use complex SQL
expressions:

```php
$this->table('date_ranges')
    ->addColumn('start_date', 'date')
    ->addColumn('end_date', 'date')
    ->addColumn('discount', 'decimal', ['precision' => 5, 'scale' => 2])
    ->addCheckConstraint('valid_date_range', 'end_date >= start_date')
    ->addCheckConstraint('valid_discount', 'discount BETWEEN 0 AND 100')
    ->save();
```

### Checking and Dropping Check Constraints

Use `hasCheckConstraint()` to check whether a constraint exists, and
`dropCheckConstraint()` to remove one:

```php
$table = $this->table('products');
if ($table->hasCheckConstraint('price_positive')) {
    $table->dropCheckConstraint('price_positive')
        ->save();
}
```

### Database-Specific Behavior

- MySQL stores check constraint metadata in
  `INFORMATION_SCHEMA.CHECK_CONSTRAINTS`
- PostgreSQL stores constraints in `pg_constraint`
- SQLite recreates the table when altering check constraints
- SQL Server support is planned for a future release

## Next Steps

- [Columns and Table Operations](columns-and-table-operations)
- [Schema Introspection and Platform Limitations](schema-introspection-and-platform-limitations)
