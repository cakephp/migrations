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
namespace Migrations\Util;

use Cake\Core\Plugin;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Trait gathering useful methods needed in various places of the plugin
 */
trait UtilTrait
{

    /**
     * Get the migrations or seeds files path based on the current InputInterface
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input of the current command.
     * @param string $default Default folder to set if no source option is found in the $input param
     * @return string
     */
    public function getPath(InputInterface $input, $default = 'Migrations')
    {
        $folder = $input->getOption('source') ?: $default;

        $dir = ROOT . DS . 'config' . DS . $folder;
        $plugin = $this->getPlugin($input);

        if ($plugin !== null) {
            $dir = Plugin::path($plugin) . 'config' . DS . $folder;
        }

        return $dir;
    }

    /**
     * Get the plugin name based on the current InputInterface
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input of the current command.
     * @return string|null
     */
    public function getPlugin(InputInterface $input)
    {
        $plugin = $input->getOption('plugin') ?: null;
        return $plugin;
    }
}