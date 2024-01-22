<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Command;

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

    /**
     * @var string
     */
    public $connection;

    /**
     * @param string $className
     * @param string|null $pluginName
     * @return string[]
     */
    public function fetchTableName($className, $pluginName = null)
    {
        return $this->publicFetchTableName($className, $pluginName);
    }

    /**
     * @param string|null $pluginName
     * @return array<int<0, max>, null|string>
     */
    public function getTableNames($pluginName = null)
    {
        return $this->publicGetTableNames($pluginName);
    }
}
