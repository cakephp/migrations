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
namespace Migrations\Util;

use Cake\Utility\Inflector;

/**
 * Trait gathering useful methods needed in various places of the plugin
 */
trait UtilTrait
{
    /**
     * Get the phinx table name used to store migrations data
     *
     * @param string|null $plugin Plugin name
     * @return string
     */
    protected function getPhinxTable(?string $plugin = null): string
    {
        $table = 'phinxlog';

        if (!$plugin) {
            return $table;
        }

        $plugin = Inflector::underscore($plugin) . '_';
        $plugin = str_replace(['\\', '/', '.'], '_', $plugin);

        return $plugin . $table;
    }
}
