<?php
/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Shell;

use Cake\Console\Shell;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\Core\Plugin;
use Cake\ORM\TableRegistry;


class SeedsShell extends Shell
{
/**
 * The connection being used.
 *
 * @var string
 */
	public $connection = 'default';

	public function startup() {
		parent::startup();
		Configure::write('debug', true);
		Cache::disable();

		if (isset($this->params['connection'])) {
			$this->connection = $this->params['connection'];
		}
	}

	public function import($seed = '') {
		$files = [];
		if ($seed){
			list($plugin, $model) = pluginSplit($seed);
			if(!isset($this->params['plugin'])) $this->params['plugin'] = $plugin;
			$files = $this->getDataFiles($model);
		} else {
			$files = $this->getDataFiles();
		}

		foreach($files as $files){
			$this->loadData($files);
		}

	}

/**
 * Defines what options can be passed to the shell.
 * This is required becuase CakePHP validates the passed options
 * and would complain if something not configured here is present
 *
 * @return Cake\Console\ConsoleOptionParser
 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		return $parser->description(
				'Manipulate initial data dor CakePHP tables.'
			)->addSubcommand('import', [
				'help' => 'Import seed data from files.',
			])->addArgument('name', [
				'help' => 'Name of the seed to bake. Can use Plugin.name to bake plugin seeds.'
			])->addOption('plugin', [
				'short' => 'p',
				'help' => 'Plugin to use when seeding.'
			])->addOption('connection', [
				'help' => 'Database connection to use in conjunction with `bake all`.',
				'short' => 'c',
				'default' => 'default'
			]);
	}

	protected function getDataFiles($target = '') {
		$files = [];
		$path = APP.'config'.DS.'Data';
		if (isset($this->params['plugin'])&&!empty($this->params['plugin'])) {
			$path = Plugin::path($this->params['plugin']).'config'.DS.'Data';
		}
		$dir = new Folder($path, false);
		$expr = '.*\.php';
		if(!empty($target)) $expr = $target.'\.php';
		$files = $dir->find($expr);
		foreach($files as $i=>$file){
			$files[$i] = [
				'class' => basename($path.DS.$file, ".php").'Data',
				'path' => $path.DS.$file
			];
		}

		return $files;
	}

	protected function loadData($file) {

		if (!class_exists($file['class'])) {
			include ($file['path']);
			$dataObject = new $file['class'];
		}

		$tableName = substr($file['class'], 0, -4);
		if (isset($this->params['plugin'])&&!empty($this->params['plugin'])) {
			$tableName = $this->params['plugin'].'.'.$tableName;
		}
		if(isset($classVars['model'])&&!empty($classVars['model'])){
			$tableName = $classVars['model'];
		}
		$table = TableRegistry::get($tableName);
		foreach (get_object_vars($dataObject) as $data){
			if(!is_array($data)) continue;
			foreach ($data as $row){
				if (method_exists($dataObject, 'change')){
					$row = $dataObject->change($row);
				}
				$row = $table->newEntity($row);
				$table->save($row);
			}
		}
		$this->out('Data for '.$tableName.' successfully seeded');
	}

}