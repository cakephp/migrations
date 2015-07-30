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
namespace Migrations;

use Phinx\Migration\Manager;

/**
 * Overrides Phinx Manager class in order to provide an interface
 * for running migrations within an app
 */
class CakeManager extends Manager
{

    /**
     * Reset the migrations stored in the object
     *
     * @return void
     */
    public function resetMigrations()
    {
        $this->migrations = null;
    }

    /**
     * Prints the specified environment's migration status.
     *
     * @param string $environment
     * @param null|string $format
     * @return array Array of migrations
     */
    public function printStatus($environment, $format = null)
    {
        $migrations = [];
        if (count($this->getMigrations())) {
            $env = $this->getEnvironment($environment);
            $versions = $env->getVersions();

            foreach ($this->getMigrations() as $migration) {
                if (in_array($migration->getVersion(), $versions)) {
                    $status = 'up';
                    unset($versions[array_search($migration->getVersion(), $versions)]);
                } else {
                    $status = 'down';
                }

                $migrations[] = [
                    'status' => $status,
                    'id' => $migration->getVersion(),
                    'name' => $migration->getName()
                ];
            }

            foreach ($versions as $missing) {
                $migrations[] = ['status' => 'up', 'id' => $missing, 'name' => false];
            }
        }

        if ($format === 'json') {
            $migrations = json_encode($migrations);
        }

        return $migrations;
    }

    /**
     * Checks if the migration with version number $version as already been mark migrated
     *
     * @param int|string $version Version number of the migration to check
     * @return bool
     */
    public function isMigrated($version)
    {
        $adapter = $this->getEnvironment('default')->getAdapter();
        $versions = array_flip($adapter->getVersions());

        return isset($versions[$version]);
    }

    /**
     * Marks migration with version number $version migrated
     *
     * @param int|string $version Version number of the migration to check
     * @param string $path Path where the migration file is located
     * @return bool True if success
     */
    public function markMigrated($version, $path)
    {
        $adapter = $this->getEnvironment('default')->getAdapter();

        $migrationFile = glob($path . DS . $version . '*');

        if (empty($migrationFile)) {
            throw new \RuntimeException(
                sprintf('A migration file matching version number `%s` could not be found', $version)
            );
        }

        $migrationFile = $migrationFile[0];
        $className = $this->getMigrationClassName($migrationFile);
        require_once $migrationFile;
        $Migration = new $className($version);

        $time = date('Y-m-d H:i:s', time());

        $adapter->migrated($Migration, 'up', $time, $time);
        return true;
    }

    /**
     * Resolves a migration class name based on $path
     *
     * @param string $path Path to the migration file of which we want the class name
     * @return string Migration class name
     */
    protected function getMigrationClassName($path)
    {
        $class = preg_replace('/^[0-9]+_/', '', basename($path));
        $class = str_replace('_', ' ', $class);
        $class = ucwords($class);
        $class = str_replace(' ', '', $class);
        if (strpos($class, '.') !== false) {
            $class = substr($class, 0, strpos($class, '.'));
        }

        return $class;
    }
}
