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
 * Overides Phinx Manager class in order to provide a interface
 * for running migrations within an app
 */
class CakeManager extends Manager {

    /**
     * Prints the specified environment's migration status.
     *
     * @param string $environment
     * @param null $format
     * @return array Array of migrations
     */
    public function printStatus($environment, $format = null)
    {
        $migrations = array();
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

                $migrations[] = ['status' => $status, 'id' => $migration->getVersion(), 'name' => $migration->getName()];
            }

            foreach ($versions as $missing) {
                $migrations[] = ['status' => 'up', 'id' => $missing, 'name' => false];
            }
        }

        return $migrations;
    }
}
