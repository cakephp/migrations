<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Exception;
use InvalidArgumentException;
use Migrations\Db\Literal;
use Migrations\Db\Table;
use Migrations\Db\Table\Column;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base Abstract Database Adapter.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @var \Symfony\Component\Console\Input\InputInterface|null
     */
    protected ?InputInterface $input = null;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected OutputInterface $output;

    /**
     * @var string[]
     */
    protected array $createdTables = [];

    /**
     * @var string
     */
    protected string $schemaTableName = 'phinxlog';

    /**
     * @var array
     */
    protected array $dataDomain = [];

    /**
     * Class Constructor.
     *
     * @param array<string, mixed> $options Options
     * @param \Symfony\Component\Console\Input\InputInterface|null $input Input Interface
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output Output Interface
     */
    public function __construct(array $options, ?InputInterface $input = null, ?OutputInterface $output = null)
    {
        $this->setOptions($options);
        if ($input !== null) {
            $this->setInput($input);
        }
        if ($output !== null) {
            $this->setOutput($output);
        }
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options): AdapterInterface
    {
        $this->options = $options;

        if (isset($options['migration_table'])) {
            $this->setSchemaTableName($options['migration_table']);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * @inheritDoc
     */
    public function getOption(string $name): mixed
    {
        if (!$this->hasOption($name)) {
            return null;
        }

        return $this->options[$name];
    }

    /**
     * @inheritDoc
     */
    public function setInput(InputInterface $input): AdapterInterface
    {
        $this->input = $input;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInput(): ?InputInterface
    {
        return $this->input;
    }

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output): AdapterInterface
    {
        $this->output = $output;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOutput(): OutputInterface
    {
        if (!isset($this->output)) {
            $output = new NullOutput();
            $this->setOutput($output);
        }

        return $this->output;
    }

    /**
     * @inheritDoc
     * @return array<int>
     */
    public function getVersions(): array
    {
        $rows = $this->getVersionLog();

        return array_keys($rows);
    }

    /**
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName(): string
    {
        return $this->schemaTableName;
    }

    /**
     * Sets the schema table name.
     *
     * @param string $schemaTableName Schema Table Name
     * @return $this
     */
    public function setSchemaTableName(string $schemaTableName)
    {
        $this->schemaTableName = $schemaTableName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getColumnForType(string $columnName, string $type, array $options): Column
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options);

        return $column;
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @return void
     */
    public function createSchemaTable(): void
    {
        try {
            $options = [
                'id' => false,
                'primary_key' => 'version',
            ];

            $table = new Table($this->getSchemaTableName(), $options, $this);
            $table->addColumn('version', 'biginteger', ['null' => false])
                ->addColumn('migration_name', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                ->addColumn('start_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('end_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('breakpoint', 'boolean', ['default' => false, 'null' => false])
                ->save();
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                'There was a problem creating the schema table: ' . $exception->getMessage(),
                (int)$exception->getCode(),
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getAdapterType(): string
    {
        return $this->getOption('adapter');
    }

    /**
     * @inheritDoc
     */
    public function isValidColumnType(Column $column): bool
    {
        return $column->getType() instanceof Literal || in_array($column->getType(), $this->getColumnTypes(), true);
    }

    /**
     * Determines if instead of executing queries a dump to standard output is needed
     *
     * @return bool
     */
    public function isDryRunEnabled(): bool
    {
        /** @var \Symfony\Component\Console\Input\InputInterface|null $input */
        $input = $this->getInput();

        return $input && $input->hasOption('dry-run') ? (bool)$input->getOption('dry-run') : false;
    }

    /**
     * Adds user-created tables (e.g. not phinxlog) to a cached list
     *
     * @param string $tableName The name of the table
     * @return void
     */
    protected function addCreatedTable(string $tableName): void
    {
        $tableName = $this->quoteTableName($tableName);
        if (substr_compare($tableName, 'phinxlog', -strlen('phinxlog')) !== 0) {
            $this->createdTables[] = $tableName;
        }
    }

    /**
     * Updates the name of the cached table
     *
     * @param string $tableName Original name of the table
     * @param string $newTableName New name of the table
     * @return void
     */
    protected function updateCreatedTableName(string $tableName, string $newTableName): void
    {
        $tableName = $this->quoteTableName($tableName);
        $newTableName = $this->quoteTableName($newTableName);
        $key = array_search($tableName, $this->createdTables, true);
        if ($key !== false) {
            $this->createdTables[$key] = $newTableName;
        }
    }

    /**
     * Removes table from the cached created list
     *
     * @param string $tableName The name of the table
     * @return void
     */
    protected function removeCreatedTable(string $tableName): void
    {
        $tableName = $this->quoteTableName($tableName);
        $key = array_search($tableName, $this->createdTables, true);
        if ($key !== false) {
            unset($this->createdTables[$key]);
        }
    }

    /**
     * Check if the table is in the cached list of created tables
     *
     * @param string $tableName The name of the table
     * @return bool
     */
    protected function hasCreatedTable(string $tableName): bool
    {
        $tableName = $this->quoteTableName($tableName);

        return in_array($tableName, $this->createdTables, true);
    }
}
