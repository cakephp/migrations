# Creating Migrations

Migration files are stored in the `config/Migrations` directory of your
application. The names of the migration files are prefixed with the date in
which they were created, in the format
`YYYYMMDDHHMMSS_MigrationName.php`.

Examples:

- `20160121163850_CreateProducts.php`
- `20160210133047_AddRatingToProducts.php`

The easiest way to create a migration file is by using `bin/cake bake migration`:

```bash
bin/cake bake migration CreateProducts
```

This creates an empty migration that you can edit to add any columns, indexes,
and foreign keys you need. See [Writing Migrations](../guides/writing-migrations)
for more information on using `Table` objects to define schema changes.

> [!NOTE]
> Migrations need to be applied using `bin/cake migrations migrate` after they
> have been created.

## Migration File Names

When generating a migration, you can follow one of the following patterns to
have additional skeleton code generated:

- `/^(Create)(.*)/` Creates the specified table.
- `/^(Drop)(.*)/` Drops the specified table and ignores specified field arguments.
- `/^(Add).*(?:To)(.*)/` Adds fields to the specified table.
- `/^(Remove).*(?:From)(.*)/` Removes fields from the specified table.
- `/^(Alter)(.*)/` Alters the specified table. An alias for create-table and
  add-field generation.
- `/^(Alter).*(?:On)(.*)/` Alters fields from the specified table.

You can also use the `underscore_form` as the name for your migrations, such as
`create_products`.

> [!WARNING]
> Migration names are used as class names, and thus may collide with other
> migrations if the class names are not unique. In that case, you may need to
> rename the migration manually.

## Anonymous Migration Classes

Migrations also supports generating anonymous migration classes, which use PHP's
anonymous class feature instead of named classes. This style is useful for:

- Avoiding namespace declarations
- Better PHPCS compatibility (no class name to filename matching required)
- Simpler file structure without named class constraints
- More readable filenames like `2024_12_08_120000_CreateProducts.php`

To generate an anonymous migration class, use the `--style anonymous` option:

```bash
bin/cake bake migration CreateProducts --style anonymous
```

This generates a migration file using an anonymous class:

```php
<?php
declare(strict_types=1);

use Migrations\BaseMigration;

return new class extends BaseMigration
{
    public function change(): void
    {
    }
};
```

Both traditional and anonymous migration classes work identically at runtime
and can be used interchangeably within the same project.

You can set the default migration style globally in your application
configuration:

```php
// In config/app.php or config/app_local.php
'Migrations' => [
    'style' => 'anonymous',  // or 'traditional'
],
```

This configuration also applies to seeds, allowing you to use consistent
styling across your entire project.

## Creating a Table

You can use `bake migration` to create a table:

```bash
bin/cake bake migration CreateProducts name:string description:text created modified
```

The command above will generate a migration file that resembles:

```php
<?php
use Migrations\BaseMigration;

class CreateProducts extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/guides/writing-migrations/migration-methods.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('products');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->create();
    }
}
```

## Column Syntax

The `bake migration` command provides a compact syntax to define columns when
generating a migration:

```bash
bin/cake bake migration CreateProducts name:string description:text created modified
```

You can use the column syntax when creating tables and adding columns. You can
also edit the generated migration afterwards to customize the columns further.

Columns on the command line follow this pattern:

```text
fieldName:fieldType?[length]:default[value]:indexType:indexName
```

Examples of valid email field definitions:

- `email:string?`
- `email:string:unique`
- `email:string?[50]`
- `email:string:unique:EMAIL_INDEX`
- `email:string[120]:unique:EMAIL_INDEX`

While defining decimal columns, the `length` can include precision and scale:

- `amount:decimal[5,2]`
- `amount:decimal?[5,2]`

Columns with a question mark after the field type are nullable.

The `length` part is optional and should always be written between brackets.

The `default[value]` part is optional and sets the default value for the
column. Supported value types include:

- Booleans: `true` or `false` such as `active:boolean:default[true]`
- Integers: `0`, `123`, `-456` such as `count:integer:default[0]`
- Floats: `1.5`, `-2.75` such as `rate:decimal:default[1.5]`
- Strings: `'hello'` or `"world"` such as
  `status:string:default['pending']`
- Null: `null` or `NULL` such as `description:text?:default[null]`
- SQL expressions: `CURRENT_TIMESTAMP` such as
  `created_at:datetime:default[CURRENT_TIMESTAMP]`

Fields named `created` and `modified`, as well as any field with an `_at`
suffix, will automatically be set to the type `datetime`.

There are some heuristics for choosing field types when left unspecified or set
to an invalid value. The default field type is `string`:

- `id`: `integer`
- `created`, `modified`, `updated`: `datetime`
- `latitude`, `longitude`, `lat`, `lng`: `decimal`

Additionally, you can create an empty migration file if you want full control
over what needs to be executed by omitting column definitions:

```bash
bin/cake migrations create MyCustomMigration
```

## Adding Columns to an Existing Table

If the migration name is of the form `AddXXXToYYY` and is followed by a list of
column names and types, a migration file containing the code for creating the
columns will be generated:

```bash
bin/cake bake migration AddPriceToProducts price:decimal[5,2]
```

This generates:

```php
<?php
use Migrations\BaseMigration;

class AddPriceToProducts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('products');
        $table->addColumn('price', 'decimal', [
            'default' => null,
            'null' => false,
            'precision' => 5,
            'scale' => 2,
        ]);
        $table->update();
    }
}
```

## Adding a Column with an Index

It is also possible to add indexes to columns:

```bash
bin/cake bake migration AddNameIndexToProducts name:string:index
```

This will generate:

```php
<?php
use Migrations\BaseMigration;

class AddNameIndexToProducts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('products');
        $table->addColumn('name', 'string')
              ->addColumn('email', 'string')
              ->addIndex(['name'])
              // add a unique index:
              ->addIndex('email', ['unique' => true])
              ->update();
    }
}
```

## Adding a Column with a Default Value

You can specify default values for columns using the `default[value]` syntax:

```bash
bin/cake bake migration AddActiveToUsers active:boolean:default[true]
```

This will generate:

```php
<?php
use Migrations\BaseMigration;

class AddActiveToUsers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('active', 'boolean', [
            'default' => true,
            'null' => false,
        ]);
        $table->update();
    }
}
```

You can combine default values with other options like nullable and indexes:

```bash
bin/cake bake migration AddStatusToOrders status:string:default['pending']:unique
```

## Altering a Column

In the same way, you can generate a migration to alter a column if the
migration name is of the form `AlterXXXOnYYY`:

```bash
bin/cake bake migration AlterPriceOnProducts name:float
```

This will generate:

```php
<?php
use Migrations\BaseMigration;

class AlterPriceOnProducts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('products');
        $table->changeColumn('name', 'float');
        $table->update();
    }
}
```

> [!WARNING]
> Changing the type of a column can result in data loss if the current and
> target column type are not compatible. For example, converting a varchar to a
> float.

## Removing a Column

In the same way, you can generate a migration to remove a column if the
migration name is of the form `RemoveXXXFromYYY`:

```bash
bin/cake bake migration RemovePriceFromProducts price
```

This creates:

```php
<?php
use Migrations\BaseMigration;

class RemovePriceFromProducts extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('products');
        $table->removeColumn('price')
              ->save();
    }
}
```

> [!NOTE]
> `removeColumn()` is not reversible, so it must be called in the `up()`
> method. Add a corresponding `addColumn()` call to the `down()` method.
