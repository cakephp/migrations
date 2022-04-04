<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Utility\Inflector;
use Migrations\Util\ColumnParser;

/**
 * Command class for generating migration snapshot files.
 */
class BakeMigrationCommand extends BakeSimpleMigrationCommand
{
    /**
     * @var string
     */
    protected $_name;

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'bake migration';
    }

    /**
     * @inheritDoc
     */
    public function bake(string $name, Arguments $args, ConsoleIo $io): void
    {
        EventManager::instance()->on('Bake.initialize', function (Event $event) {
            $event->getSubject()->loadHelper('Migrations.Migration');
        });
        $this->_name = $name;

        parent::bake($name, $args, $io);
    }

    /**
     * @inheritDoc
     */
    public function template(): string
    {
        return 'Migrations.config/skeleton';
    }

    /**
     * @inheritDoc
     */
    public function templateData(Arguments $arguments): array
    {
        $className = $this->_name;
        $namespace = Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
            $pluginPath = $this->plugin . '.';
        }

        $action = $this->detectAction($className);

        if (empty($action)) {
            return [
                'plugin' => $this->plugin,
                'pluginPath' => $pluginPath,
                'namespace' => $namespace,
                'tables' => [],
                'action' => null,
                'name' => $className,
            ];
        }

        $arguments = $arguments->getArguments();
        unset($arguments[0]);
        $columnParser = new ColumnParser();
        $fields = $columnParser->parseFields($arguments);
        $indexes = $columnParser->parseIndexes($arguments);
        $primaryKey = $columnParser->parsePrimaryKey($arguments);

        if (in_array($action[0], ['alter_table', 'add_field'], true) && !empty($primaryKey)) {
            $this->io->abort('Adding a primary key to an already existing table is not supported.');
        }

        [$action, $table] = $action;

        return [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $namespace,
            'tables' => [$table],
            'action' => $action,
            'columns' => [
                'fields' => $fields,
                'indexes' => $indexes,
                'primaryKey' => $primaryKey,
            ],
            'name' => $className,
        ];
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();
        $text = <<<'TEXT'
Create a blank or generated migration. Using the name of the migration 
Operations and table names will be inferred.

<info>Examples</info>

<warning>bin/cake bake migration CreateUsers</warning>

This command will generate a migration that creates
a table named users.

<warning>bin/cake bake migration DropGroups</warning>
This command will generate a migration that drops
the groups table.

<warning>bin/cake bake migration AlterUsers</warning>
This command will generate a migration that alters the users table.

<warning>bin/cake bake migration AddFieldToUsers role:string</warning>
This command will generate a migration that adds a 'role' field
with a 'string' type to the users table. Migrations that operate
on columns can use the <info>Column Grammar</info> to describe
the column in detail.

<warning>bin/cake bake migration AlterFieldOnUsers role</warning>
These commands will generate a migration that will alter the 'role'
field on the users table.

<warning>bin/cake bake migration RemoveFieldsFromUsers role</warning>
<warning>bin/cake bake migration RemoveRoleFromUsers</warning>
These commands will generate a migration that will remove the 'role'
field on the users table.

<info>Column Grammar</info>

When describing columns you can use the following syntax:

<warning>{name}:{primary}{type}{nullable}[{length}]:{index}</warning>

All sections other than name are optional.

* The types are the abstract database column types in CakePHP.
* The <warning>?</warning> value indicates if a column is nullable.
  e.x. `role:string?`.
* Length option must be enclosed in `[]` e.x. `name:string[100]`
* The `index` attribute can define the column as having a unique
  key with `unique` or a primary key with `primary`.
TEXT;

        $parser->setDescription($text);

        return $parser;
    }

    /**
     * Detects the action and table from the name of a migration
     *
     * @param string $name Name of migration
     * @return array
     */
    public function detectAction($name)
    {
        if (preg_match('/^(Create|Drop)(.*)/', $name, $matches)) {
            $action = strtolower($matches[1]) . '_table';
            $table = Inflector::underscore($matches[2]);
        } elseif (preg_match('/^(Add).+?(?:To)(.*)/', $name, $matches)) {
            $action = 'add_field';
            $table = Inflector::underscore($matches[2]);
        } elseif (preg_match('/^(Remove).+?(?:From)(.*)/', $name, $matches)) {
            $action = 'drop_field';
            $table = Inflector::underscore($matches[2]);
        } elseif (preg_match('/^(Alter).+?(?:On)(.*)/', $name, $matches)) {
            $action = 'alter_field';
            $table = Inflector::underscore($matches[2]);
        } elseif (preg_match('/^(Alter)(.*)/', $name, $matches)) {
            $action = 'alter_table';
            $table = Inflector::underscore($matches[2]);
        } else {
            return [];
        }

        return [$action, $table];
    }
}
