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
namespace Migrations\Test\TestCase\Shell;

use Migrations\TableFinderTrait;

/**
 * Class TestClassWithSnapshotTrait
 * Used to expose public methods from the SnapshotTrait in order to test their behaviors
 */
class TestClassWithSnapshotTrait
{
    use TableFinderTrait {
        fetchTableName as public publicFetchTableName;
        getTableNames as public publicGetTableNames;
    }

    public $connection;

    public function fetchTableName($className, $pluginName = null)
    {
        return $this->publicFetchTableName($className, $pluginName);
    }

    public function getTableNames($pluginName = null)
    {
        return $this->publicGetTableNames($pluginName);
    }
}
