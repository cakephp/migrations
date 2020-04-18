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
namespace Migrations;

use Cake\Console\BaseCommand;
use Cake\Console\CommandCollection;
use Cake\Console\CommandInterface;
use Cake\Core\BasePlugin;
use Cake\Core\Plugin as CorePlugin;
use Cake\Filesystem\Filesystem;
use Cake\Utility\Inflector;

/**
 * Plugin class for migrations
 */
class Plugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string
     */
    protected $name = 'Migrations';

    /**
     * Don't try to load routes.
     *
     * @var bool
     */
    protected $routesEnabled = false;

    /**
     * Add migrations commands.
     *
     * @param \Cake\Console\CommandCollection $collection The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $collection): CommandCollection
    {
        if (class_exists('Bake\Command\SimpleBakeCommand')) {
            $commands = $collection->discoverPlugin($this->getName());

            return $collection->addMany($commands);
        }
        if (!CorePlugin::isLoaded($this->name)) {
            return [];
        }
        $path = CorePlugin::classPath($this->name);
        $namespace = str_replace('/', '\\', $this->name);
        $prefix = Inflector::underscore($this->name) . '.';

        $commands = $this->scanDir($path . 'Command', $namespace . '\Command\\', $prefix);
        $commands = $this->resolveNames($commands, $collection);

        return $collection->addMany($commands);
    }

    /**
     * Scan a directory for .php files and return the class names that
     * should be within them.
     * and ignore bake commands.
     *
     * @param string $path The directory to read.
     * @param string $namespace The namespace the commands live in.
     * @param string $prefix The prefix to apply to commands for their full name.
     * @return array The list of shell info arrays based on scanning the filesystem and inflection.
     */
    protected function scanDir(string $path, string $namespace, string $prefix): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $classPattern = '/Command\.php$/';
        $fs = new Filesystem();
        /** @var \SplFileInfo[] $files */
        $files = $fs->find($path, $classPattern);

        $commands = [];
        foreach ($files as $fileInfo) {
            $file = $fileInfo->getFilename();
            $part = substr($file, 0, 4);
            // ignore bake commands
            if (strtolower($part) === 'bake') {
                continue;
            }

            $name = Inflector::underscore(preg_replace($classPattern, '', $file));

            $class = $namespace . $fileInfo->getBasename('.php');
            /** @psalm-suppress DeprecatedClass */
            if (!is_subclass_of($class, CommandInterface::class)) {
                continue;
            }
            if (is_subclass_of($class, BaseCommand::class)) {
                $name = $class::defaultName();
            }
            $commands[$path . DS . $file] = [
                'fullName' => $prefix . $name,
                'name' => $name,
                'class' => $class,
            ];
        }

        ksort($commands);

        return array_values($commands);
    }

    /**
     * Resolve names based on existing commands
     *
     * @param array $input The results of a CommandScanner operation.
     * @param \Cake\Console\CommandCollection $collection The command collection
     * @return string[] A flat map of command names => class names.
     */
    protected function resolveNames(array $input, CommandCollection $collection): array
    {
        $out = [];
        foreach ($input as $info) {
            $name = $info['name'];
            $addLong = $name !== $info['fullName'];

            // If the short name has been used, use the full name.
            // This allows app shells to have name preference.
            // and app shells to overwrite core shells.
            if ($collection->has($name) && $addLong) {
                $name = $info['fullName'];
            }

            $out[$name] = $info['class'];
            if ($addLong) {
                $out[$info['fullName']] = $info['class'];
            }
        }

        return $out;
    }
}
