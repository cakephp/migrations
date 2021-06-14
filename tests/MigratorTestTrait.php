<?php
declare(strict_types=1);

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

namespace Migrations\Test;

use Cake\Datasource\ConnectionManager;

trait MigratorTestTrait
{
    public function setDummyConnections(): void
    {
        $testConfig = ['url' => getenv('DB_URL')];
        $testConfig['migrations'] = [
            ['source' => 'FooSource'],
            ['plugin' => 'FooPlugin'],
        ];

        $this->setConfigIfNotDefined('test_migrator', $testConfig);

        $testConfig['migrations'] = ['plugin' => 'BarPlugin'];
        $this->setConfigIfNotDefined('test_migrator_2', $testConfig);

        $testConfig['migrations'] = true;
        $this->setConfigIfNotDefined('test_migrator_3', $testConfig);
    }

    public function setConfigIfNotDefined(string $name, array $config): void
    {
        if (ConnectionManager::getConfig($name) === null) {
            ConnectionManager::setConfig($name, $config);
        }
    }
}
