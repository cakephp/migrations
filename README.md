# Migrations plugin for CakePHP

[![CI](https://github.com/cakephp/migrations/actions/workflows/ci.yml/badge.svg)](https://github.com/cakephp/migrations/actions/workflows/ci.yml)
[![Coverage Status](https://img.shields.io/codecov/c/github/cakephp/migrations/5.x.svg?style=flat-square)](https://app.codecov.io/github/cakephp/migrations/tree/5.x)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/migrations.svg?style=flat-square)](https://packagist.org/packages/cakephp/migrations)

This is a Database Migrations system for CakePHP.

The plugin provides a complete database migration solution with support for creating, running, and managing migrations.

This branch is for use with CakePHP **6.x**, requiring PHP 8.4 or higher. See [version map](https://github.com/cakephp/migrations/wiki#version-map) for details.

When upgrading from CakePHP 5, regenerate schema dump files with `bin/cake migrations dump`
before baking a diff. Serialized schema metadata uses CakePHP 6's property names.

## Installation

You can install this plugin into your CakePHP application using [Composer](https://getcomposer.org).

Run the following command
```sh
composer require cakephp/migrations:"6.x-dev"
 ```

## Configuration

You can load the plugin using the shell command:

```
bin/cake plugin load Migrations --only-cli
```

If you are using the PendingMigrations middleware, use:
```
bin/cake plugin load Migrations
```

## Documentation

Full documentation of the plugin can be found on the [CakePHP Cookbook](https://book.cakephp.org/migrations/5/).
