<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Command;

use Bake\Command\SimpleBakeCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;

/**
 * Task class for generating seed files.
 *
 * @property \Bake\Command\TestCommand $Test
 */
class BakeSeedCommand extends SimpleBakeCommand
{
    /**
     * path to Migration directory
     */
    public string $pathFragment = 'config/Seeds/';

    protected string $_name;

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Create a migration seed.';
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'bake seed';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'seed';
    }

    /**
     * @inheritDoc
     */
    public function fileName($name): string
    {
        return Inflector::camelize($name) . 'Seed.php';
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        $path = ROOT . DS . $this->pathFragment;
        if ($this->plugin) {
            $path = $this->pluginPath($this->plugin) . $this->pathFragment;
        }

        return str_replace('/', DS, $path);
    }

    /**
     * @inheritDoc
     */
    public function template(): string
    {
        $style = $this->args->getOption('style') ?? Configure::read('Migrations.style', 'traditional');
        if ($style === 'anonymous') {
            return 'Migrations.Seed/seed-anonymous';
        }

        return 'Migrations.Seed/seed';
    }

    /**
     * Get template data.
     *
     * @return array
     * @phpstan-return array<string, mixed>
     */
    public function templateData(): array
    {
        $namespace = Configure::read('App.namespace');
        if ($this->plugin) {
            $namespace = $this->pluginNamespace($this->plugin);
        }

        $table = Inflector::underscore((string)$this->args->getArgumentAt(0));
        if ($this->args->hasOption('table')) {
            /** @var string $table */
            $table = $this->args->getOption('table');
        }

        $records = false;
        if ($this->args->getOption('data')) {
            $limit = (int)$this->args->getOption('limit');

            $fields = (string)$this->args->getOption('fields') ?: '*';
            if ($fields !== '*') {
                $fields = explode(',', $fields);
            }
            $model = $this->getTableLocator()->get('BakeSeed', [
                'table' => $table,
                'connection' => ConnectionManager::get($this->connection),
            ]);

            $query = $model->find('all')
                ->enableHydration(false);

            if ($limit) {
                $query->limit($limit);
            }
            if ($fields !== '*') {
                $query->select($fields);
            }

            /** @var array $records */
            $records = $query->disableResultsCasting()->toArray();

            $records = $this->prettifyArray($records);
        }

        return [
            'className' => $this->_name,
            'namespace' => $namespace,
            'records' => $records,
            'table' => $table,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function bake(string $name): void
    {
        /** @var array<string, bool|string|null> $options */
        $options = array_merge($this->args->getOptions(), ['no-test' => true]);
        $newArgs = new Arguments(
            $this->args->getArguments(),
            $options,
            ['name'],
        );
        $this->_name = $name;
        $this->args = $newArgs;
        parent::bake($name);
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser to update.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->setDescription(
            'Bake seed class.',
        )->addOption('table', [
            'help' => 'The database table to use.',
        ])->addOption('data', [
            'boolean' => true,
            'help' => 'Include data from the table to the seed',
        ])->addOption('fields', [
            'default' => '*',
            'help' => 'If including data, comma separated list of fields to select (all fields by default)',
        ])->addOption('limit', [
            'short' => 'l',
            'help' => 'If including data, max number of rows to select',
        ])->addOption('style', [
            'help' => 'Seed style to use (traditional or anonymous).',
            'default' => null,
            'choices' => ['traditional', 'anonymous'],
        ]);

        return $parser;
    }

    /**
     * Prettify var_export of an array output
     *
     * @param array $array              Array to prettify
     * @param int $tabCount             Initial tab count
     * @param string $indentCharacter   Desired indent for the code.
     * @return string
     */
    protected function prettifyArray(array $array, int $tabCount = 3, string $indentCharacter = '    '): string
    {
        $content = var_export($array, true);

        $lines = explode("\n", $content);

        $inString = false;
        $removeKeys = [];

        foreach ($lines as $k => &$line) {
            if ($k === 0) {
                // First row
                $line = '[';
                continue;
            }

            if ($k === count($lines) - 1) {
                // Last row
                $line = str_repeat($indentCharacter, --$tabCount) . ']';
                continue;
            }

            $line = ltrim($line);

            if (!$inString) {
                if ($line === '),') {
                    // Check for closing bracket
                    $line = '],';
                    $tabCount--;
                } elseif (preg_match("/^\d+\s\=\>\s$/", $line)) {
                    // Mark '0 =>' kind of lines to remove
                    $removeKeys[] = $k;
                    continue;
                }

                //Insert tab count
                $line = str_repeat($indentCharacter, $tabCount) . $line;
            }

            $length = strlen($line);
            for ($j = 0; $j < $length; $j++) {
                if ($line[$j] === '\\') {
                    // skip character right after an escape \
                    $j++;
                } elseif ($line[$j] === "'") {
                    // check string open/end
                    $inString = !$inString;
                }
            }

            // check for opening bracket
            if (!$inString && trim($line) === 'array (') {
                $line = str_replace('array (', '[', $line);
                $tabCount++;
            }
        }
        unset($line);

        foreach ($removeKeys as $key) {
            unset($lines[$key]);
        }

        return implode("\n", $lines);
    }
}
