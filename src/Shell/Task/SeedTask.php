<?php
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
namespace Migrations\Shell\Task;

use Bake\Shell\Task\SimpleBakeTask;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Utility\Inflector;

/**
 * Task class for generating seed files.
 */
class SeedTask extends SimpleBakeTask
{
    /**
     * path to Migration directory
     *
     * @var string
     */
    public $pathFragment = 'config/Seeds/';

    /**
     * {@inheritDoc}
     */
    public function name()
    {
        return 'seed';
    }

    /**
     * {@inheritDoc}
     */
    public function fileName($name)
    {
        return Inflector::camelize($name) . 'Seed.php';
    }

    /**
     * {@inheritDoc}
     */
    public function getPath()
    {
        $path = ROOT . DS . $this->pathFragment;
        if (isset($this->plugin)) {
            $path = $this->_pluginPath($this->plugin) . $this->pathFragment;
        }
        return str_replace('/', DS, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function template()
    {
        return 'Migrations.Seed/seed';
    }

    /**
     * Get template data.
     *
     * @return array
     */
    public function templateData()
    {
        $namespace = Configure::read('App.namespace');
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
        }

        $table = Inflector::tableize($this->args[0]);
        if (!empty($this->params['table'])) {
            $table = $this->params['table'];
        }

        return [
            'className' => $this->BakeTemplate->viewVars['name'],
            'namespace' => $namespace,
            'records' => false,
            'table' => $table,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function bake($name)
    {
        $this->params['no-test'] = true;
        return parent::bake($name);
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $name = ($this->plugin ? $this->plugin . '.' : '') . $this->name;
        $parser = new ConsoleOptionParser($name);

        $bakeThemes = [];
        foreach (Plugin::loaded() as $plugin) {
            $path = Plugin::classPath($plugin);
            if (is_dir($path . 'Template' . DS . 'Bake')) {
                $bakeThemes[] = $plugin;
            }
        }

        $parser->description(
            'Bake seed class.'
        )->addOption('plugin', [
            'short' => 'p',
            'help' => 'Plugin to bake into.'
        ])->addOption('force', [
            'short' => 'f',
            'boolean' => true,
            'help' => 'Force overwriting existing files without prompting.'
        ])->addOption('connection', [
            'short' => 'c',
            'default' => 'default',
            'help' => 'The datasource connection to get data from.'
        ])->addOption('table', [
            'help' => 'The database table to use.'
        ])->addOption('theme', [
            'short' => 't',
            'help' => 'The theme to use when baking code.',
            'choices' => $bakeThemes
        ])->addArgument('name', [
            'help' => 'Name of the seed to bake. Can use Plugin.name to bake plugin models.'
        ]);

        return $parser;
    }
}
