# Migrations plugin for CakePHP

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://img.shields.io/travis/cakephp/migrations/master.svg?style=flat-square)](https://travis-ci.org/cakephp/migrations)
[![Coverage Status](https://img.shields.io/codecov/c/github/cakephp/migrations/master.svg?style=flat-square)](https://codecov.io/github/cakephp/migrations?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/migrations.svg?style=flat-square)](https://packagist.org/packages/cakephp/migrations)

This is a Database Migrations system for CakePHP.

The plugin consists of a CakePHP shell wrapper for the [Phinx](https://book.cakephp.org/phinx/0/en/index.html) migrations library.

This branch is for use with CakePHP **4.x**. See [version map](https://github.com/cakephp/migrations/wiki#version-map) for details.

## Installation

You can install this plugin into your CakePHP application using [Composer](http://getcomposer.org).

Run the following command
```sh
composer require cakephp/migrations
 ```

## Configuration

You can load the plugin using the shell command:

```
bin/cake plugin load Migrations
```

Or you can manually add the loading statement in the **src/Application.php** file of your application:
```php
public function bootstrap()
{
    parent::bootstrap();
    $this->addPlugin('Migrations');
}
```

Additionally, you will need to configure the ``default`` database configuration in your **config/app.php** file.

## Documentation

Full documentation of the plugin can be found on the [CakePHP Cookbook](https://book.cakephp.org/migrations/2/).
