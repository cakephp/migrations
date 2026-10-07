# Writing Migrations

Migrations are a declarative API that helps you transform your database. Each
migration is represented by a PHP class in a unique file. It is preferred that
you write your migrations using the Migrations API, but raw SQL is also
supported.

For generating migration files with `bake`, naming patterns, anonymous
migration classes, and command-line column syntax, see
[Creating Migrations](../../getting-started/creating-migrations).

## Guide Map

This section has been split into focused reference pages:

- [Migration Methods](migration-methods)
- [Columns and Table Operations](columns-and-table-operations)
- [Indexes and Constraints](indexes-and-constraints)
- [Schema Introspection and Platform Limitations](schema-introspection-and-platform-limitations)

Use these pages as the API reference for authoring migrations.
