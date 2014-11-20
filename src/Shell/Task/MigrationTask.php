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

use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\File;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Shell\Task\BakeTask;
use Cake\Utility\Inflector;

/**
 * Task class for generating migrations files.
 */
class MigrationTask extends BakeTask {

/**
 * path to Migration directory
 *
 * @var string
 */
	public $pathFragment = 'config/Migrations/';

/**
 * tasks
 *
 * @var array
 */
	public $tasks = ['DbConfig', 'Template'];

/**
 * Tables to skip
 *
 * @var array
 */
	public $skipTables = ['i18n', 'phinxlog'];

/**
 * Regex of Table name to skip
 *
 * @var string
 */
	public $skipTablesRegex = '_phinxlog';

/**
 * Connexion
 */
	protected $_connexion;

/**
 * Execution method always used for tasks
 *
 * @param string $name The name of the migration file to bake.
 * @return void
 */
	public function main($name = null) {
		parent::main();

		if (empty($name)) {
			$this->out('Choose a migration name to bake in underscore format');
			return true;
		}

		$name = $this->_getName($name);

		// replace whitespaces by underscores _
		$name = str_replace(" ", "_", $name);

		// check name of migration
		if (!preg_match('/^[a-z0-9-]+$/', $name)) {
			$this->out('The filename is not correct. The filename can only contain "a-z", "0-9" and "-". Also the files must be lowercase.');
			return true;
		}

		$this->bake($name);
	}

/**
 * Generate code for the given migration name.
 *
 * @param string $name The migration name to generate.
 * @return void
 */
	public function bake($filename) {
		$this->_connexion = $this->getConnection();

		$collection = $this->getCollection($this->_connexion);

		$data = compact(
		'collection'
		);

		$this->bakeMigration($filename, $data);
	}

/**
 * Bake a migration class.
 *
 * @param \Cake\ORM\Table $model Model name or object
 * @param array $data An array to use to generate the Table
 * @return string
 */
	public function bakeMigration($filename, array $data = []) {
		$ns = Configure::read('App.namespace');
		$pluginPath = '';
		if ($this->plugin) {
			$ns = $this->plugin;
			$pluginPath = $this->plugin . '.';
		}

		$migration = $this->getMigration();

		$data += [
			'plugin' => $this->plugin,
			'pluginPath' => $pluginPath,
			'namespace' => $ns,
			'migration' => $migration,
			'name' => $filename
		];

		$this->Template->set($data);

		$out = $this->Template->generate($this->getTheme() . '.classes/migration');

		$path = dirname(APP) . DS . $this->pathFragment;
		if (isset($this->plugin)) {
			$path = $this->_pluginPath($this->plugin) . $this->pathFragment;
		}
		$path = str_replace('/', DS, $path);
		$filename = $path . date('YmdHis') . '_' . $filename . '.php';
		$message = "\n" . 'Baking migration class for Connection ' . $this->getConnection();
		if (!empty($this->plugin)):
			$message .=  ' (Plugin : ' . $this->plugin . ')';
		endif;
		$this->out($message, 1, Shell::QUIET);
		$this->createFile($filename, $out);
		return $out;
	}

/**
 * Get the connection name : default connexion or parameters
 *
 * @return string
 */
	public function getConnection() {
		if (!empty($this->params['connection'])) {
			return $this->params['connection'];
		}
		return 'default';
	}

/**
 * Get the theme name : default migration or parameters
 *
 * @return string
 */
	public function getTheme() {
		if (!empty($this->params['theme'])) {
			return $this->params['theme'];
		}
		return 'Migrations';
	}

/**
 * Get the model option : default true or parameters
 *
 * @return string
 */
	public function checkModel() {
		if (!empty($this->params['checkModel'])) {
			return $this->params['checkModel'];
		}
		return true;
	}

/**
 * Get a collection from a database
 *
 * @param $connexion : database connexion name
 */
	public function getCollection($connexion) {
		$db = ConnectionManager::get($connexion);
		// Create a schema collection.
		return $db->schemaCollection();
	}

/**
 * Generate HTML migration change function
 *
 * @return $migration html
 */
	public function getMigration() {
		$migration = '';

		// Get the table names
		$collection = $this->getCollection($this->_connexion);

		$tables = $collection->listTables();
		foreach ($tables as $table):
			if ((!in_array($table, $this->skipTables)) && (strpos($table, $this->skipTablesRegex) === false)):
				if ($this->modelToAdd($table, $this->plugin)):
					$migration .= "\$table = \$this->table($table);";
					// Get a single table (instance of Schema\Table)
					$tableSchema = $collection->describe($table);
					// columns of the table
					$columns = $tableSchema->columns();
					$migration .= "\n\t";
					$migration .= "\$table";
					foreach ($columns as $column):
						$migration .= "->addColumn('" . $column . "', '" . $tableSchema->columnType($column) . "', [";
						foreach ($tableSchema->column($column) as $optionName => $option):
							if (in_array($optionName, ['length', 'limit', 'default', 'unsigned', 'null'])):
								$migration .= "'" . str_replace('length', 'limit', $optionName) . "' => '" .  $option . "', ";
							endif;
						endforeach;
						$migration .= "])";
						$migration .= "\n\t\t\t";
					endforeach;
					$migration .= "->save();";
					$migration .= "\n\n\t";
				endif;
			endif;
		endforeach;
		return $migration;
	}

/**
 * To check if a Table Model is to be added in the migration file
 *
 * @param $tableName Table name in underscore case
 * @param $pluginName Plugin name if exists
 * @return bool true if the model is to be added
 */
	public function modelToAdd($tableName, $pluginName = null) {
		// Check only if option set to true
		if ($this->checkModel() === true):
			if (!$this->modelExist($tableName, $pluginName)):
			return false;
			endif;
		endif;

		return true;
	}

/**
 * To check if a Table Model exists in the path of model
 *
 * @param $tableName Table name in underscore case
 * @param $pluginName Plugin name if exists
 * @return bool
 */
	public function modelExist($tableName, $pluginName = null) {
		$file = new File($this->getModelPath($pluginName) . Inflector::pluralize(Inflector::classify($tableName)) . 'Table.php');
		if ($file->exists()):
		return true;
		endif;
		return false;
	}

/**
 * Path for Table folder
 *
 * @param $pluginName Plugin name if exists
 * @return path to Table Folder. Default to App Table Path
 */
	public function getModelPath($pluginName = null) {
		if (!is_null($pluginName) && Plugin::loaded($pluginName)):
		return Plugin::classPath($pluginName) . 'Model' . DS . 'Table' . DS;
		endif;
		return APP . 'Model' . DS . 'Table' . DS;
	}

/**
 * Gets the option parser instance and configures it.
 *
 * @return \Cake\Console\ConsoleOptionParser
 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();

		$parser->description(
		'Bake migration class.'
		)->addArgument('name', [
			'help' => 'Name of the migration to bake. Can use Plugin.name to bake plugin migrations.',
			'required' => true
		])->addOption('connection', [
			'short' => 'c',
			'default' => 'default',
			'help' => 'The datasource connection to get data from.'
		])->addOption('checkModel', [
			'default' => true,
			'help' => 'If model is set to true, check also that the model exists.'
		])->addOption('theme', [
			'short' => 't',
			'default' => 'Migrations',
			'help' => 'The theme to use when baking code.'
		])->addOption('plugin', [
			'short' => 'p',
			'help' => 'Plugin to bake into.'
		])->epilog(
			'Omitting all arguments and options will list the options for and arguments for the plugin'
		);

		return $parser;
	}

}
