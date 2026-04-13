# Using the Query Builder

It is not uncommon to pair database structure changes with data changes. For example, you may want to
migrate the data in a couple columns from the users to a newly created table. For this type of scenarios,
Migrations provides access to a Query builder object, that you may use to execute complex `SELECT`, `UPDATE`,
`INSERT` or `DELETE` statements.

The Query builder is provided by the [cakephp/database](https://github.com/cakephp/database) project, and should
be easy to work with as it resembles very closely plain SQL. Accesing the query builder is done by calling the
`getQueryBuilder(string $type)` function. The `string $type` options are <span class="title-ref">'select'</span>, <span class="title-ref">'insert'</span>, <span class="title-ref">'update'</span> and \`'delete'\`:

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
        $builder = $this->getQueryBuilder('select');
        $statement = $builder->select('*')->from('users')->execute();
        var_dump($statement->fetchAll());
    }
}
```

## Selecting Fields

Adding fields to the SELECT clause:

```php
<?php
$builder->select(['id', 'title', 'body']);

// Results in SELECT id AS pk, title AS aliased_title, body ...
$builder->select(['pk' => 'id', 'aliased_title' => 'title', 'body']);

// Use a closure
$builder->select(function ($builder) {
    return ['id', 'title', 'body'];
});
```

## Where Conditions

Generating conditions:

```php
// WHERE id = 1
$builder->where(['id' => 1]);

// WHERE id > 1
$builder->where(['id >' => 1]);
```

As you can see you can use any operator by placing it with a space after the field name. Adding multiple conditions is easy as well:

```php
<?php
$builder->where(['id >' => 1])->andWhere(['title' => 'My Title']);

// Equivalent to
$builder->where(['id >' => 1, 'title' => 'My title']);

// WHERE id > 1 OR title = 'My title'
$builder->where(['OR' => ['id >' => 1, 'title' => 'My title']]);
```

For even more complex conditions you can use closures and expression objects:

```php
<?php
// Coditions are tied together with AND by default
$builder
    ->select('*')
    ->from('articles')
    ->where(function ($exp) {
        return $exp
            ->eq('author_id', 2)
            ->eq('published', true)
            ->notEq('spam', true)
            ->gt('view_count', 10);
    });
```

Which results in:

```sql
SELECT * FROM articles
WHERE
    author_id = 2
    AND published = 1
    AND spam != 1
    AND view_count > 10
```

Combining expressions is also possible:

```php
<?php
$builder
    ->select('*')
    ->from('articles')
    ->where(function ($exp) {
        $orConditions = $exp->or_(['author_id' => 2])
            ->eq('author_id', 5);
        return $exp
            ->not($orConditions)
            ->lte('view_count', 10);
    });
```

It generates:

```sql
SELECT *
FROM articles
WHERE
    NOT (author_id = 2 OR author_id = 5)
    AND view_count <= 10
```

When using the expression objects you can use the following methods to create conditions:

- `eq()` Creates an equality condition.
- `notEq()` Create an inequality condition
- `like()` Create a condition using the `LIKE` operator.
- `notLike()` Create a negated `LIKE` condition.
- `in()` Create a condition using `IN`.
- `notIn()` Create a negated condition using `IN`.
- `gt()` Create a `>` condition.
- `gte()` Create a `>=` condition.
- `lt()` Create a `<` condition.
- `lte()` Create a `<=` condition.
- `isNull()` Create an `IS NULL` condition.
- `isNotNull()` Create a negated `IS NULL` condition.

## Aggregates and SQL Functions

```php
<?php
// Results in SELECT COUNT(*) count FROM ...
$builder->select(['count' => $builder->func()->count('*')]);
```

A number of commonly used functions can be created with the func() method:

- `sum()` Calculate a sum. The arguments will be treated as literal values.
- `avg()` Calculate an average. The arguments will be treated as literal values.
- `min()` Calculate the min of a column. The arguments will be treated as literal values.
- `max()` Calculate the max of a column. The arguments will be treated as literal values.
- `count()` Calculate the count. The arguments will be treated as literal values.
- `concat()` Concatenate two values together. The arguments are treated as bound parameters unless marked as literal.
- `coalesce()` Coalesce values. The arguments are treated as bound parameters unless marked as literal.
- `dateDiff()` Get the difference between two dates/times. The arguments are treated as bound parameters unless marked as literal.
- `now()` Take either 'time' or 'date' as an argument allowing you to get either the current time, or current date.

When providing arguments for SQL functions, there are two kinds of parameters you can use,
literal arguments and bound parameters. Literal parameters allow you to reference columns or
other SQL literals. Bound parameters can be used to safely add user data to SQL functions. For example:

```php
<?php
// Generates:
// SELECT CONCAT(title, ' NEW') ...;
$concat = $builder->func()->concat([
    'title' => 'literal',
    ' NEW'
]);
$query->select(['title' => $concat]);
```

## Getting Results out of a Query

Once you’ve made your query, you’ll want to retrieve rows from it. There are a few ways of doing this:

```php
<?php
// Iterate the query
foreach ($builder as $row) {
    echo $row['title'];
}

// Get the statement and fetch all results
$results = $builder->execute()->fetchAll('assoc');
```

## Creating an Insert Query

Creating insert queries is also possible:

```php
<?php
$builder = $this->getQueryBuilder('insert');
$builder
    ->insert(['first_name', 'last_name'])
    ->into('users')
    ->values(['first_name' => 'Steve', 'last_name' => 'Jobs'])
    ->values(['first_name' => 'Jon', 'last_name' => 'Snow'])
    ->execute();
```

For increased performance, you can use another builder object as the values for an insert query:

```php
<?php

$namesQuery = $this->getQueryBuilder('select');
$namesQuery
    ->select(['fname', 'lname'])
    ->from('users')
    ->where(['is_active' => true]);

$builder = $this->getQueryBuilder('insert');
$st = $builder
    ->insert(['first_name', 'last_name'])
    ->into('names')
    ->values($namesQuery)
    ->execute();

var_dump($st->lastInsertId('names', 'id'));
```

The above code will generate:

```sql
INSERT INTO names (first_name, last_name)
    (SELECT fname, lname FROM USERS where is_active = 1)
```

## Creating an update Query

Creating update queries is similar to both inserting and selecting:

```php
<?php
$builder = $this->getQueryBuilder('update');
$builder
    ->update('users')
    ->set('fname', 'Snow')
    ->where(['fname' => 'Jon'])
    ->execute();
```

## Creating a Delete Query

Finally, delete queries:

```php
<?php
$builder = $this->getQueryBuilder('delete');
$builder
    ->delete('users')
    ->where(['accepted_gdpr' => false])
    ->execute();
```
