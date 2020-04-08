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
namespace Migrations\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

/**
 * Trait needed for all "snapshot" type of bake operations.
 * Snapshot type operations are : baking a snapshot and baking a diff.
 */
trait SnapshotTrait
{
    /**
     * @inheritDoc
     */
    protected function createFile(string $path, string $contents, Arguments $args, ConsoleIo $io): bool
    {
        $createFile = parent::createFile($path, $contents, $args, $io);

        if ($createFile) {
            $this->markSnapshotApplied($path, $args, $io);

            if (!$args->getOption('no-lock')) {
                $this->refreshDump($args, $io);
            }
        }

        return $createFile;
    }

    /**
     * Will mark a snapshot created, the snapshot being identified by its
     * full file path.
     *
     * @param string $path Path to the newly created snapshot
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    protected function markSnapshotApplied($path, Arguments $args, ConsoleIo $io)
    {
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        [$version, ] = explode('_', $fileName, 2);

        $newArgs = [];
        $newArgs[] = '-t';
        $newArgs[] = $version;
        $newArgs[] = '-o';

        if ($args->getOption('connection')) {
            $newArgs[] = '-c';
            $newArgs[] = $args->getOption('connection');
        }

        if ($args->getOption('plugin')) {
            $newArgs[] = '-p';
            $newArgs[] = $args->getOption('plugin');
        }

        $io->out('Marking the migration ' . $fileName . ' as migrated...');
        $this->executeCommand(MigrationsMarkMigratedCommand::class, $newArgs, $io);
    }

    /**
     * After a file has been successfully created, we refresh the dump of the database
     * to be able to generate a new diff afterward.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    protected function refreshDump(Arguments $args, ConsoleIo $io)
    {
        $newArgs = [];

        if ($args->getOption('connection')) {
            $newArgs[] = '-c';
            $newArgs[] = $args->getOption('connection');
        }

        if ($args->getOption('plugin')) {
            $newArgs[] = '-p';
            $newArgs[] = $args->getOption('plugin');
        }

        $io->out('Creating a dump of the new database state...');
        $this->executeCommand(MigrationsDumpCommand::class, $newArgs, $io);
    }
}
