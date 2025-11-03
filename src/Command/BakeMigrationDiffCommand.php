<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Database\Schema\CollectionInterface;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Migrations\Migration\ManagerFactory;
use Migrations\Util\TableFinder;
use Migrations\Util\UtilTrait;

/**
 * Task class for generating migration diff files.
 */
class BakeMigrationDiffCommand extends BakeSimpleMigrationCommand
{
    use SnapshotTrait;
    use UtilTrait;

    /**
     * Array of migrations that have already been migrated
     *
     * @var array
     */
    protected array $migratedItems = [];

    /**
     * Path to the migration files
     *
     * @var string
     */
    protected string $migrationsPath;

    /**
     * Migration files that are stored in the self::migrationsPath
     *
     * @var array
     */
    protected array $migrationsFiles = [];

    /**
     * Name of the phinx log table
     *
     * @var string
     */
    protected string $phinxTable;

    /**
     * List the tables the connection currently holds
     *
     * @var array<string>
     */
    protected array $tables = [];

    /**
     * Array of \Cake\Database\Schema\TableSchemaInterface objects from the dump file which
     * represents the state of the database after the last migrate / rollback command
     *
     * @var array<string, \Cake\Database\Schema\TableSchemaInterface>
     */
    protected array $dumpSchema;

    /**
     * Array of \Cake\Database\Schema\TableSchemaInterface objects from the current state of the database
     *
     * @var array<string, \Cake\Database\Schema\TableSchemaInterface>
     */
    protected array $currentSchema;

    /**
     * List of the tables that are commonly found in the dump schema and the current schema
     *
     * @var array<string, \Cake\Database\Schema\TableSchemaInterface>
     */
    protected array $commonTables;

    /**
     * @var array<string, array>
     */
    protected array $templateData = [];

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'bake migration_diff';
    }

    /**
     * @inheritDoc
     */
    public function bake(string $name, Arguments $args, ConsoleIo $io): void
    {
        $this->setup($args);

        if (!$this->checkSync()) {
            $io->abort('Your migrations history is not in sync with your migrations files. ' .
                'Make sure all your migrations have been migrated before baking a diff.');
        }

        if (!$this->migrationsFiles && !$this->migratedItems) {
            $this->bakeSnapshot($name, $args, $io);
        }

        $collection = $this->getCollection($this->connection);

        $connection = ConnectionManager::get($this->connection);
        assert($connection instanceof Connection);

        EventManager::instance()->on('Bake.initialize', function (Event $event) use ($collection, $connection): void {
            $event->getSubject()->loadHelper('Migrations.Migration', [
                'collection' => $collection,
                'connection' => $connection,
            ]);
        });

        parent::bake($name, $args, $io);
    }

    /**
     * Sets up everything the baking process needs
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @return void
     */
    protected function setup(Arguments $args): void
    {
        $this->migrationsPath = $this->getPath($args);
        $this->migrationsFiles = glob($this->migrationsPath . '*.php') ?: [];
        $this->phinxTable = $this->getPhinxTable($this->plugin);

        $connection = ConnectionManager::get($this->connection);
        assert($connection instanceof Connection);
        $this->tables = $connection->getSchemaCollection()->listTables();
        $tableExists = in_array($this->phinxTable, $this->tables, true);

        $migratedItems = [];
        if ($tableExists) {
            $query = $connection->selectQuery();
            /** @var array $migratedItems */
            $migratedItems = $query
                ->select(['version'])
                ->from($this->phinxTable)
                ->orderBy(['version DESC'])
                ->execute()->fetchAll('assoc');
        }

        $this->migratedItems = $migratedItems;
    }

    /**
     * Get a collection from a database.
     *
     * @param string $connection Database connection name.
     * @return \Cake\Database\Schema\CollectionInterface
     */
    public function getCollection(string $connection): CollectionInterface
    {
        $connection = ConnectionManager::get($connection);
        assert($connection instanceof Connection);

        return $connection->getSchemaCollection();
    }

    /**
     * @inheritDoc
     */
    public function templateData(Arguments $arguments): array
    {
        $this->dumpSchema = $this->getDumpSchema($arguments);
        $this->currentSchema = $this->getCurrentSchema();
        $this->commonTables = array_intersect_key($this->currentSchema, $this->dumpSchema);

        $this->calculateDiff();

        return [
            'data' => $this->templateData,
            'dumpSchema' => $this->dumpSchema,
            'currentSchema' => $this->currentSchema,
        ];
    }

    /**
     * This method runs the various methods needed to calculate a diff between the current
     * state of the database and the schema dump file.
     *
     * @return void
     */
    protected function calculateDiff(): void
    {
        $this->getConstraints();
        $this->getIndexes();
        $this->getColumns();
        $this->getTables();
    }

    /**
     * Calculate the diff between the current state of the database and the schema dump
     * by returning an array containing the full \Cake\Database\Schema\TableSchemaInterface definitions
     * of tables to be created and removed in the diff file.
     *
     * The method directly sets the diff in a property of the class.
     *
     * @return void
     */
    protected function getTables(): void
    {
        $this->templateData['fullTables'] = [
            'add' => array_diff_key($this->currentSchema, $this->dumpSchema),
            'remove' => array_diff_key($this->dumpSchema, $this->currentSchema),
        ];
    }

    /**
     * Calculate the diff between columns in existing tables.
     * This will look for columns addition, columns removal and changes in columns metadata
     * such as change of types or property such as length.
     *
     * Note that the method is not able to detect columns name change.
     * The method directly sets the diff in a property of the class.
     *
     * @return void
     */
    protected function getColumns(): void
    {
        foreach ($this->commonTables as $table => $currentSchema) {
            $currentColumns = $currentSchema->columns();
            $oldColumns = $this->dumpSchema[$table]->columns();

            // brand new columns
            $addedColumns = array_diff($currentColumns, $oldColumns);
            foreach ($addedColumns as $columnName) {
                $column = $currentSchema->getColumn($columnName);
                /** @var int $key */
                $key = array_search($columnName, $currentColumns);
                if ($key > 0) {
                    $column['after'] = $currentColumns[$key - 1];
                }
                if (isset($column['unsigned'])) {
                    $column['signed'] = !$column['unsigned'];
                    unset($column['unsigned']);
                }
                $this->templateData[$table]['columns']['add'][$columnName] = $column;
            }

            // changes in columns meta-data
            foreach ($currentColumns as $columnName) {
                $column = $currentSchema->getColumn($columnName);
                $oldColumn = $this->dumpSchema[$table]->getColumn($columnName);
                unset(
                    $column['collate'],
                    $column['fixed'],
                    $oldColumn['collate'],
                    $oldColumn['fixed'],
                );

                if (
                    in_array($columnName, $oldColumns, true) &&
                    $column !== $oldColumn
                ) {
                    $changedAttributes = array_diff_assoc($column, $oldColumn);

                    foreach (['type', 'length', 'null', 'default'] as $attribute) {
                        $phinxAttributeName = $attribute;
                        if ($attribute === 'length') {
                            $phinxAttributeName = 'limit';
                        }
                        if (!isset($changedAttributes[$phinxAttributeName])) {
                            $changedAttributes[$phinxAttributeName] = $column[$attribute];
                        }
                    }

                    if (isset($changedAttributes['unsigned'])) {
                        $changedAttributes['signed'] = !$changedAttributes['unsigned'];
                        unset($changedAttributes['unsigned']);
                    } else {
                        // badish hack
                        if (isset($column['unsigned']) && $column['unsigned'] === true) {
                            $changedAttributes['signed'] = false;
                        }
                    }

                    // For decimal columns, handle CakePHP schema -> migration attribute mapping
                    if ($column['type'] === 'decimal') {
                        // In CakePHP schema: 'length' = precision, 'precision' = scale
                        // In migrations: 'precision' = precision, 'scale' = scale

                        // Convert CakePHP schema's 'precision' (which is scale) to migration's 'scale'
                        if (isset($changedAttributes['precision'])) {
                            $changedAttributes['scale'] = $changedAttributes['precision'];
                            unset($changedAttributes['precision']);
                        }

                        // Convert CakePHP schema's 'length' (which is precision) to migration's 'precision'
                        if (isset($changedAttributes['length'])) {
                            $changedAttributes['precision'] = $changedAttributes['length'];
                            unset($changedAttributes['length']);
                        }

                        // Ensure both precision and scale are always set for decimal columns
                        if (!isset($changedAttributes['precision']) && isset($column['length'])) {
                            $changedAttributes['precision'] = $column['length'];
                        }
                        if (!isset($changedAttributes['scale']) && isset($column['precision'])) {
                            $changedAttributes['scale'] = $column['precision'];
                        }

                        // Remove 'limit' for decimal columns as they use precision/scale instead
                        unset($changedAttributes['limit']);
                    } elseif (isset($changedAttributes['length'])) {
                        // For non-decimal columns, convert 'length' to 'limit'
                        if (!isset($changedAttributes['limit'])) {
                            $changedAttributes['limit'] = $changedAttributes['length'];
                        }
                        unset($changedAttributes['length']);
                    }

                    $this->templateData[$table]['columns']['changed'][$columnName] = $changedAttributes;
                }
            }

            // columns deletion
            if (!isset($this->templateData[$table]['columns']['remove'])) {
                $this->templateData[$table]['columns']['remove'] = [];
            }
            $removedColumns = array_diff($oldColumns, $currentColumns);
            if ($removedColumns) {
                foreach ($removedColumns as $columnName) {
                    $column = $this->dumpSchema[$table]->getColumn($columnName);
                    /** @var int $key */
                    $key = array_search($columnName, $oldColumns);
                    if ($key > 0) {
                        $column['after'] = $oldColumns[$key - 1];
                    }
                    $this->templateData[$table]['columns']['remove'][$columnName] = $column;
                }
            }
        }
    }

    /**
     * Calculate the diff between constraints in existing tables.
     * This will look for constraints addition, constraints removal and changes in constraints metadata
     * such as change of referenced columns if the old constraints and the new one have the same name.
     *
     * The method directly sets the diff in a property of the class.
     *
     * @return void
     */
    protected function getConstraints(): void
    {
        foreach ($this->commonTables as $table => $currentSchema) {
            $currentConstraints = $currentSchema->constraints();
            $oldConstraints = $this->dumpSchema[$table]->constraints();

            // brand new constraints
            $addedConstraints = array_diff($currentConstraints, $oldConstraints);
            foreach ($addedConstraints as $constraintName) {
                $this->templateData[$table]['constraints']['add'][$constraintName] =
                    $currentSchema->getConstraint($constraintName);
                $constraint = $currentSchema->getConstraint($constraintName);
                if ($constraint['type'] === TableSchema::CONSTRAINT_FOREIGN) {
                    $this->templateData[$table]['constraints']['add'][$constraintName] = $constraint;
                } else {
                    $this->templateData[$table]['indexes']['add'][$constraintName] = $constraint;
                }
            }

            // constraints having the same name between new and old schema
            // if present in both, check if they are the same : if not, remove the old one and add the new one
            foreach ($currentConstraints as $constraintName) {
                $constraint = $currentSchema->getConstraint($constraintName);

                if (
                    in_array($constraintName, $oldConstraints, true) &&
                    $constraint !== $this->dumpSchema[$table]->getConstraint($constraintName)
                ) {
                    $this->templateData[$table]['constraints']['remove'][$constraintName] =
                        $this->dumpSchema[$table]->getConstraint($constraintName);
                    $this->templateData[$table]['constraints']['add'][$constraintName] =
                        $constraint;
                }
            }

            // removed constraints
            $removedConstraints = array_diff($oldConstraints, $currentConstraints);
            foreach ($removedConstraints as $constraintName) {
                $constraint = $this->dumpSchema[$table]->getConstraint($constraintName);
                if ($constraint['type'] === TableSchema::CONSTRAINT_FOREIGN) {
                    $this->templateData[$table]['constraints']['remove'][$constraintName] = $constraint;
                } else {
                    $this->templateData[$table]['indexes']['remove'][$constraintName] = $constraint;
                }
            }
        }
    }

    /**
     * Calculate the diff between indexes in existing tables.
     * This will look for indexes addition, indexes removal and changes in indexes metadata
     * such as change of referenced columns if the old indexes and the new one have the same name.
     *
     * The method directly sets the diff in a property of the class.
     *
     * @return void
     */
    protected function getIndexes(): void
    {
        foreach ($this->commonTables as $table => $currentSchema) {
            $currentIndexes = $currentSchema->indexes();
            $oldIndexes = $this->dumpSchema[$table]->indexes();
            sort($currentIndexes);
            sort($oldIndexes);

            // brand new indexes
            $addedIndexes = array_diff($currentIndexes, $oldIndexes);
            foreach ($addedIndexes as $indexName) {
                $this->templateData[$table]['indexes']['add'][$indexName] = $currentSchema->getIndex($indexName);
            }

            // indexes having the same name between new and old schema
            // if present in both, check if they are the same : if not, remove the old one and add the new one
            foreach ($currentIndexes as $indexName) {
                $index = $currentSchema->getIndex($indexName);

                if (
                    in_array($indexName, $oldIndexes, true) &&
                    $index !== $this->dumpSchema[$table]->getIndex($indexName)
                ) {
                    $this->templateData[$table]['indexes']['remove'][$indexName] =
                        $this->dumpSchema[$table]->getIndex($indexName);
                    $this->templateData[$table]['indexes']['add'][$indexName] = $index;
                }
            }

            // indexes deletion
            if (!isset($this->templateData[$table]['indexes']['remove'])) {
                $this->templateData[$table]['indexes']['remove'] = [];
            }

            $removedIndexes = array_diff($oldIndexes, $currentIndexes);
            $parts = [];
            if ($removedIndexes) {
                foreach ($removedIndexes as $index) {
                    $parts[$index] = $this->dumpSchema[$table]->getIndex($index);
                }
            }
            $this->templateData[$table]['indexes']['remove'] = array_merge(
                $this->templateData[$table]['indexes']['remove'],
                $parts,
            );
        }
    }

    /**
     * Checks that the migrations history is in sync with the migrations files
     *
     * @return bool Whether migrations history is sync or not
     */
    protected function checkSync(): bool
    {
        if (!$this->migrationsFiles && !$this->migratedItems) {
            return true;
        }

        if ($this->migratedItems) {
            $lastVersion = $this->migratedItems[0]['version'];
            $lastFile = end($this->migrationsFiles);

            return $lastFile && (bool)strpos($lastFile, (string)$lastVersion);
        }

        return false;
    }

    /**
     * Fallback method called to bake a snapshot when the phinx log history is empty and
     * there are no migration files.
     *
     * @param string $name Name.
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null Value of the snapshot baking dispatch process
     */
    protected function bakeSnapshot(string $name, Arguments $args, ConsoleIo $io): ?int
    {
        $io->out('Your migrations history is empty and you do not have any migrations files.');
        $io->out('Falling back to baking a snapshot...');
        $newArgs = [];
        $newArgs[] = $name;

        $newArgs = array_merge($newArgs, $this->parseOptions($args));
        $exitCode = $this->executeCommand(BakeMigrationSnapshotCommand::class, $newArgs, $io);

        if ($exitCode === 1) {
            $io->abort('Something went wrong during the snapshot baking. Please try again.');
        }

        return $exitCode;
    }

    /**
     * Fetch the correct schema dump based on the arguments and options passed to the shell call
     * and returns it as an array
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @return array<string, \Cake\Database\Schema\TableSchemaInterface> Full database schema.
     */
    protected function getDumpSchema(Arguments $args): array
    {
        $options = [];

        $connectionName = 'default';
        if ($args->getOption('connection')) {
            $connectionName = $inputArgs['--connection'] = $args->getOption('connection');
        }
        $options['connection'] = $connectionName;
        $options['source'] = $args->getOption('source');
        $options['plugin'] = $args->getOption('plugin');

        $factory = new ManagerFactory($options);
        $config = $factory->createConfig();

        $path = $config->getMigrationPath() . DS . 'schema-dump-' . $connectionName . '.lock';
        if (!file_exists($path)) {
            $msg = 'Unable to retrieve the schema dump file. You can create a dump file using ' .
                'the `cake migrations dump` command';
            $this->io->abort($msg);
        }

        return unserialize((string)file_get_contents($path));
    }

    /**
     * Reflects the current database schema.
     *
     * @return array<string, \Cake\Database\Schema\TableSchemaInterface> Full database schema.
     */
    protected function getCurrentSchema(): array
    {
        $schema = [];

        if (!$this->tables) {
            return $schema;
        }

        $connection = ConnectionManager::get($this->connection);
        assert($connection instanceof Connection);
        $connection->cacheMetadata(false);
        $collection = $connection->getSchemaCollection();

        // Filter tables based on plugin option
        $tablesToDescribe = $this->tables;
        if ($this->plugin) {
            $tableFinder = new TableFinder($this->connection);
            $options = [
                'plugin' => $this->plugin,
                'require-table' => true,
            ];
            $tablesToDescribe = $tableFinder->getTablesToBake($collection, $options);
        }

        foreach ($tablesToDescribe as $table) {
            if (preg_match('/^.*phinxlog$/', $table) === 1) {
                continue;
            }

            $schema[$table] = $collection->describe($table);
        }

        return $schema;
    }

    /**
     * @inheritDoc
     */
    public function template(): string
    {
        return 'Migrations.config/diff';
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();

        $parser->setDescription(
            'Create a migration that captures the difference between ' .
            'the migration state is expected to be and what the schema ' .
            'reflection contains.',
        )->addArgument('name', [
            'help' => 'Name of the migration to bake. Can use Plugin.name to bake migration files into plugins.',
            'required' => true,
        ])->addOption('generate-only', [
            'help' => 'Only generate the migration file without marking it as applied',
            'boolean' => true,
        ]);

        return $parser;
    }
}
