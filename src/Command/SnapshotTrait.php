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
namespace Migrations\Command;

/**
 * Trait needed for all "snapshot" type of bake operations.
 * Snapshot type operations are : baking a snapshot and baking a diff.
 */
trait SnapshotTrait
{
    /**
     * @inheritDoc
     */
    protected function createFile(string $path, string $contents): bool
    {
        $createFile = parent::createFile($path, $contents);

        if ($createFile && !$this->args->getOption('generate-only')) {
            $this->markSnapshotApplied($path);

            if (!$this->args->getOption('no-lock')) {
                $this->refreshDump();
            }
        }

        return $createFile;
    }

    /**
     * Will mark a snapshot created, the snapshot being identified by its
     * full file path.
     *
     * @param string $path Path to the newly created snapshot
     * @return void
     */
    protected function markSnapshotApplied(string $path): void
    {
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        [$version, ] = explode('_', $fileName, 2);

        $newArgs = [];
        $newArgs[] = '-t';
        $newArgs[] = $version;
        $newArgs[] = '-o';

        $newArgs = array_merge($newArgs, $this->parseOptions());

        $this->io->out('Marking the migration ' . $fileName . ' as migrated...');
        $this->executeCommand(MarkMigratedCommand::class, $newArgs);
    }

    /**
     * After a file has been successfully created, we refresh the dump of the database
     * to be able to generate a new diff afterward.
     *
     * @return void
     */
    protected function refreshDump(): void
    {
        $newArgs = $this->parseOptions();

        $this->io->out('Creating a dump of the new database state...');
        $this->executeCommand(DumpCommand::class, $newArgs);
    }

    /**
     * Will parse 'connection', 'plugin' and 'source' options into a new Array
     *
     * @return array Array containing the short for the option followed by its value
     */
    protected function parseOptions(): array
    {
        $newArgs = [];
        if ($this->args->getOption('connection')) {
            $newArgs[] = '-c';
            $newArgs[] = $this->args->getOption('connection');
        }

        if ($this->args->getOption('plugin')) {
            $newArgs[] = '-p';
            $newArgs[] = $this->args->getOption('plugin');
        }

        if ($this->args->getOption('source')) {
            $newArgs[] = '-s';
            $newArgs[] = $this->args->getOption('source');
        }

        return $newArgs;
    }
}
