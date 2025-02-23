<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use BadMethodCallException;
use Cake\Console\ConsoleIo;
use Cake\Database\Connection;
use Cake\Database\Query;
use Cake\Database\Query\DeleteQuery;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Query\UpdateQuery;
use Cake\Database\Schema\SchemaDialect;
use Exception;
use InvalidArgumentException;
use Migrations\Config\Config;
use Migrations\Db\Action\AddColumn;
use Migrations\Db\Action\AddForeignKey;
use Migrations\Db\Action\AddIndex;
use Migrations\Db\Action\ChangeColumn;
use Migrations\Db\Action\ChangeComment;
use Migrations\Db\Action\ChangePrimaryKey;
use Migrations\Db\Action\DropForeignKey;
use Migrations\Db\Action\DropIndex;
use Migrations\Db\Action\DropTable;
use Migrations\Db\Action\RemoveColumn;
use Migrations\Db\Action\RenameColumn;
use Migrations\Db\Action\RenameTable;
use Migrations\Db\AlterInstructions;
use Migrations\Db\Literal;
use Migrations\Db\Table;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table as TableMetadata;
use Migrations\MigrationInterface;
use Migrations\Shim\OutputAdapter;
use PDOException;
use Phinx\Util\Literal as PhinxLiteral;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base Abstract Database Adapter.
 */
abstract class AbstractAdapter implements AdapterInterface, DirectActionInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected ConsoleIo $io;

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
     * @var \Cake\Database\Connection|null
     */
    protected ?Connection $connection = null;

    /**
     * Class Constructor.
     *
     * @param array<string, mixed> $options Options
     * @param \Cake\Console\ConsoleIo|null $io Console input/output
     */
    public function __construct(array $options, ?ConsoleIo $io = null)
    {
        $this->setOptions($options);
        if ($io !== null) {
            $this->setIo($io);
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

        if (isset($options['connection']) && $options['connection'] instanceof Connection) {
            $this->setConnection($options['connection']);
        }

        return $this;
    }

    /**
     * Sets the database connection.
     *
     * @param \Cake\Database\Connection $connection Connection
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function setConnection(Connection $connection): AdapterInterface
    {
        $this->connection = $connection;

        // Create the schema table if it doesn't already exist
        if (!$this->hasTable($this->getSchemaTableName())) {
            $this->createSchemaTable();
        } else {
            $table = new Table($this->getSchemaTableName(), [], $this);
            if (!$table->hasColumn('migration_name')) {
                $table
                    ->addColumn(
                        'migration_name',
                        'string',
                        ['limit' => 100, 'after' => 'version', 'default' => null, 'null' => true]
                    )
                    ->save();
            }
            if (!$table->hasColumn('breakpoint')) {
                $table
                    ->addColumn('breakpoint', 'boolean', ['default' => false, 'null' => false])
                    ->save();
            }
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
     * Get the schema dialect for this adapter.
     *
     * @return \Cake\Database\Schema\SchemaDialect
     */
    protected function getSchemaDialect(): SchemaDialect
    {
        $driver = $this->getConnection()->getDriver();

        return $driver->schemaDialect();
    }

    /**
     * Gets the database connection
     *
     * @return \Cake\Database\Connection
     */
    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $this->connection = $this->getOption('connection');
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Backwards compatibility shim for migrations 3.x
     *
     * TODO add deprecation for this. Use getConnection() instead.
     *
     * @return \Cake\Database\Connection
     */
    public function getDecoratedConnection(): Connection
    {
        return $this->getConnection();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    public function connect(): void
    {
        $this->getConnection()->getDriver()->connect();
        $this->setConnection($this->getConnection());
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->getConnection()->getDriver()->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $this->getConnection()->begin();
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): void
    {
        $this->getConnection()->commit();
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(): void
    {
        $this->getConnection()->rollback();
    }

    /**
     * @inheritDoc
     */
    public function hasTransactions(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function quoteColumnName(string $columnName): string
    {
        $driver = $this->getConnection()->getDriver();

        return $driver->quoteIdentifier($columnName);
    }

    /**
     * Writes a message to stdout if verbose output is on
     *
     * @param string $message The message to show
     * @return void
     */
    protected function verboseLog(string $message): void
    {
        $io = $this->getIo();
        if (
            $io === null || (
                !$this->isDryRunEnabled() &&
                $io->level() != ConsoleIo::VERBOSE
            )
        ) {
            return;
        }

        $io->out($message);
    }

    /**
     * @inheritDoc
     */
    public function setInput(InputInterface $input): AdapterInterface
    {
        throw new RuntimeException('Using setInput() interface is not supported.');
    }

    /**
     * @inheritDoc
     */
    public function getInput(): ?InputInterface
    {
        throw new RuntimeException('Using getInput() interface is not supported.');
    }

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output): AdapterInterface
    {
        throw new RuntimeException('Using setInput() method is not supported');
    }

    /**
     * @inheritDoc
     */
    public function getOutput(): OutputInterface
    {
        return new OutputAdapter($this->io);
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
     * @inheritDoc
     */
    public function setIo(ConsoleIo $io)
    {
        $this->io = $io;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIo(): ?ConsoleIo
    {
        return $this->io ?? null;
    }

    /**
     * Determines if instead of executing queries a dump to standard output is needed
     *
     * @return bool
     */
    public function isDryRunEnabled(): bool
    {
        return $this->getOption('dryrun') === true;
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

    /**
     * @inheritDoc
     */
    public function execute(string $sql, array $params = []): int
    {
        $sql = rtrim($sql, "; \t\n\r\0\x0B") . ';';
        $this->verboseLog($sql);

        if ($this->isDryRunEnabled()) {
            return 0;
        }

        $connection = $this->getConnection();
        if (empty($params)) {
            $result = $connection->execute($sql);

            return $result->rowCount();
        }
        $stmt = $connection->execute($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function getQueryBuilder(string $type): Query
    {
        return match ($type) {
            Query::TYPE_SELECT => $this->getConnection()->selectQuery(),
            Query::TYPE_INSERT => $this->getConnection()->insertQuery(),
            Query::TYPE_UPDATE => $this->getConnection()->updateQuery(),
            Query::TYPE_DELETE => $this->getConnection()->deleteQuery(),
            default => throw new InvalidArgumentException(
                'Query type must be one of: `select`, `insert`, `update`, `delete`.'
            )
        };
    }

    /**
     * @inheritDoc
     */
    public function getSelectBuilder(): SelectQuery
    {
        return $this->getConnection()->selectQuery();
    }

    /**
     * @inheritDoc
     */
    public function getInsertBuilder(): InsertQuery
    {
        return $this->getConnection()->insertQuery();
    }

    /**
     * @inheritDoc
     */
    public function getUpdateBuilder(): UpdateQuery
    {
        return $this->getConnection()->updateQuery();
    }

    /**
     * @inheritDoc
     */
    public function getDeleteBuilder(): DeleteQuery
    {
        return $this->getConnection()->deleteQuery();
    }

    /**
     * Executes a query and returns PDOStatement.
     *
     * @param string $sql SQL
     * @return \Cake\Database\StatementInterface
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->getConnection()->execute($sql, $params);
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->getConnection()->execute($sql)->fetch('assoc');
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string $sql): array
    {
        return $this->getConnection()->execute($sql)->fetchAll('assoc');
    }

    /**
     * @inheritDoc
     */
    public function insert(TableMetadata $table, array $row): void
    {
        $sql = sprintf(
            'INSERT INTO %s ',
            $this->quoteTableName($table->getName())
        );
        $columns = array_keys($row);
        $sql .= '(' . implode(', ', array_map($this->quoteColumnName(...), $columns)) . ')';

        foreach ($row as $column => $value) {
            if (is_bool($value)) {
                $row[$column] = $this->castToBool($value);
            }
        }

        if ($this->isDryRunEnabled()) {
            $sql .= ' VALUES (' . implode(', ', array_map($this->quoteValue(...), $row)) . ');';
            $this->io->out($sql);
        } else {
            $values = [];
            $vals = [];
            foreach ($row as $value) {
                $placeholder = '?';
                if ($value instanceof Literal || $value instanceof PhinxLiteral) {
                    $placeholder = (string)$value;
                }
                $values[] = $placeholder;
                if ($placeholder === '?') {
                    $vals[] = $value;
                }
            }
            $sql .= ' VALUES (' . implode(',', $values) . ')';
            $this->getConnection()->execute($sql, $vals);
        }
    }

    /**
     * Quotes a database value.
     *
     * @param mixed $value The value to quote
     * @return mixed
     */
    protected function quoteValue(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return $value;
        }

        if ($value === null) {
            return 'null';
        }
        if ($value instanceof Literal || $value instanceof PhinxLiteral) {
            return (string)$value;
        }
        // TODO remove hacks like this by using cake's database layer better.
        $driver = $this->getConnection()->getDriver();
        $method = new ReflectionMethod($driver, 'getPdo');
        $method->setAccessible(true);

        return $method->invoke($driver)->quote($value);
    }

    /**
     * Quotes a database string.
     *
     * @param string $value The string to quote
     * @return string
     */
    protected function quoteString(string $value): string
    {
        // TODO remove hacks like this by using cake's database layer better.
        $driver = $this->getConnection()->getDriver();
        $method = new ReflectionMethod($driver, 'getPdo');
        $method->setAccessible(true);

        return $method->invoke($driver)->quote($value);
    }

    /**
     * @inheritDoc
     */
    public function bulkinsert(TableMetadata $table, array $rows): void
    {
        $sql = sprintf(
            'INSERT INTO %s ',
            $this->quoteTableName($table->getName())
        );
        $current = current($rows);
        $keys = array_keys($current);

        $sql .= '(' . implode(', ', array_map($this->quoteColumnName(...), $keys)) . ') VALUES ';

        if ($this->isDryRunEnabled()) {
            $values = array_map(function ($row) {
                return '(' . implode(', ', array_map($this->quoteValue(...), $row)) . ')';
            }, $rows);
            $sql .= implode(', ', $values) . ';';
            $this->io->out($sql);
        } else {
            $vals = [];
            $queries = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $v) {
                    $placeholder = '?';
                    if ($v instanceof Literal || $v instanceof PhinxLiteral) {
                        $placeholder = (string)$v;
                    }
                    $values[] = $placeholder;
                    if ($placeholder == '?') {
                        if (is_bool($v)) {
                            $vals[] = $this->castToBool($v);
                        } else {
                            $vals[] = $v;
                        }
                    }
                }
                $query = '(' . implode(', ', $values) . ')';
                $queries[] = $query;
            }
            $sql .= implode(',', $queries);
            $this->getConnection()->execute($sql, $vals);
        }
    }

    /**
     * @inheritDoc
     */
    public function getVersions(): array
    {
        $rows = $this->getVersionLog();

        return array_keys($rows);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException
     */
    public function getVersionLog(): array
    {
        $result = [];

        switch ($this->options['version_order']) {
            case Config::VERSION_ORDER_CREATION_TIME:
                $orderBy = 'version ASC';
                break;
            case Config::VERSION_ORDER_EXECUTION_TIME:
                $orderBy = 'start_time ASC, version ASC';
                break;
            default:
                throw new RuntimeException('Invalid version_order configuration option');
        }

        // This will throw an exception if doing a --dry-run without any migrations as phinxlog
        // does not exist, so in that case, we can just expect to trivially return empty set
        try {
            $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY %s', $this->quoteTableName($this->getSchemaTableName()), $orderBy));
        } catch (PDOException $e) {
            if (!$this->isDryRunEnabled()) {
                throw $e;
            }
            $rows = [];
        }

        foreach ($rows as $version) {
            $result[(int)$version['version']] = $version;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function migrated(MigrationInterface $migration, string $direction, string $startTime, string $endTime): AdapterInterface
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            // up
            $sql = sprintf(
                'INSERT INTO %s (%s, %s, %s, %s, %s) VALUES (?, ?, ?, ?, ?);',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('version'),
                $this->quoteColumnName('migration_name'),
                $this->quoteColumnName('start_time'),
                $this->quoteColumnName('end_time'),
                $this->quoteColumnName('breakpoint'),
            );
            $params = [
                $migration->getVersion(),
                substr($migration->getName(), 0, 100),
                $startTime,
                $endTime,
                $this->castToBool(false),
            ];

            $this->execute($sql, $params);
        } else {
            // down
            $sql = sprintf(
                'DELETE FROM %s WHERE %s = ?',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('version'),
            );
            $params = [$migration->getVersion()];

            $this->execute($sql, $params);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toggleBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $params = [
            $migration->getVersion(),
        ];
        $this->query(
            sprintf(
                'UPDATE %1$s SET %2$s = CASE %2$s WHEN true THEN false ELSE true END, %4$s = %4$s WHERE %3$s = ?;',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('breakpoint'),
                $this->quoteColumnName('version'),
                $this->quoteColumnName('start_time')
            ),
            $params
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resetAllBreakpoints(): int
    {
        return $this->execute(
            sprintf(
                'UPDATE %1$s SET %2$s = %3$s, %4$s = %4$s WHERE %2$s <> %3$s;',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('breakpoint'),
                $this->castToBool(false),
                $this->quoteColumnName('start_time')
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function setBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->markBreakpoint($migration, true);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function unsetBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->markBreakpoint($migration, false);

        return $this;
    }

    /**
     * Mark a migration breakpoint.
     *
     * @param \Migrations\MigrationInterface $migration The migration target for the breakpoint
     * @param bool $state The required state of the breakpoint
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    protected function markBreakpoint(MigrationInterface $migration, bool $state): AdapterInterface
    {
        $params = [
            $this->castToBool($state),
            $migration->getVersion(),
        ];
        $this->query(
            sprintf(
                'UPDATE %1$s SET %2$s = ?, %3$s = %3$s WHERE %4$s = ?;',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('breakpoint'),
                $this->quoteColumnName('start_time'),
                $this->quoteColumnName('version'),
            ),
            $params
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function createSchema(string $schemaName = 'public'): void
    {
        throw new BadMethodCallException('Creating a schema is not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function dropSchema(string $schemaName): void
    {
        throw new BadMethodCallException('Dropping a schema is not supported');
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes(): array
    {
        return [
            'string',
            'char',
            'text',
            'tinyinteger',
            'smallinteger',
            'integer',
            'biginteger',
            'bit',
            'float',
            'decimal',
            'double',
            'datetime',
            'timestamp',
            'time',
            'date',
            'blob',
            'binary',
            'varbinary',
            'boolean',
            'uuid',
            // Geospatial data types
            'geometry',
            'point',
            'linestring',
            'polygon',
        ];
    }

    /**
     * @inheritDoc
     */
    public function castToBool($value): mixed
    {
        return (bool)$value ? 1 : 0;
    }

    /**
     * Get the definition for a `DEFAULT` statement.
     *
     * @param mixed $default Default value
     * @param string|null $columnType column type added
     * @return string
     */
    protected function getDefaultValueDefinition(mixed $default, ?string $columnType = null): string
    {
        if ($default instanceof Literal) {
            $default = (string)$default;
        } elseif (is_string($default) && stripos($default, 'CURRENT_TIMESTAMP') !== 0) {
            // Ensure a defaults of CURRENT_TIMESTAMP(3) is not quoted.
            $default = $this->quoteString($default);
        } elseif (is_bool($default)) {
            $default = $this->castToBool($default);
        } elseif ($default !== null && $columnType === static::PHINX_TYPE_BOOLEAN) {
            $default = $this->castToBool((bool)$default);
        }

        return isset($default) ? " DEFAULT $default" : '';
    }

    /**
     * Executes all the ALTER TABLE instructions passed for the given table
     *
     * @param string $tableName The table name to use in the ALTER statement
     * @param \Migrations\Db\AlterInstructions $instructions The object containing the alter sequence
     * @return void
     */
    protected function executeAlterSteps(string $tableName, AlterInstructions $instructions): void
    {
        $alter = sprintf('ALTER TABLE %s %%s', $this->quoteTableName($tableName));
        $instructions->execute($alter, [$this, 'execute']);
    }

    /**
     * @inheritDoc
     */
    public function addColumn(TableMetadata $table, Column $column): void
    {
        $instructions = $this->getAddColumnInstructions($table, $column);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified column to a database table.
     *
     * @param \Migrations\Db\Table\Table $table Table
     * @param \Migrations\Db\Table\Column $column Column
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getAddColumnInstructions(TableMetadata $table, Column $column): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function renameColumn(string $tableName, string $columnName, string $newColumnName): void
    {
        $instructions = $this->getRenameColumnInstructions($tableName, $columnName, $newColumnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified column.
     *
     * @param string $tableName Table name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getRenameColumnInstructions(string $tableName, string $columnName, string $newColumnName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function changeColumn(string $tableName, string $columnName, Column $newColumn): void
    {
        $instructions = $this->getChangeColumnInstructions($tableName, $columnName, $newColumn);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to change a table column type.
     *
     * @param string $tableName Table name
     * @param string $columnName Column Name
     * @param \Migrations\Db\Table\Column $newColumn New Column
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getChangeColumnInstructions(string $tableName, string $columnName, Column $newColumn): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropColumn(string $tableName, string $columnName): void
    {
        $instructions = $this->getDropColumnInstructions($tableName, $columnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified column.
     *
     * @param string $tableName Table name
     * @param string $columnName Column Name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropColumnInstructions(string $tableName, string $columnName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function addIndex(TableMetadata $table, Index $index): void
    {
        $instructions = $this->getAddIndexInstructions($table, $index);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified index to a database table.
     *
     * @param \Migrations\Db\Table\Table $table Table
     * @param \Migrations\Db\Table\Index $index Index
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getAddIndexInstructions(TableMetadata $table, Index $index): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropIndex(string $tableName, string|array $columns): void
    {
        $instructions = $this->getDropIndexByColumnsInstructions($tableName, $columns);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified index from a database table.
     *
     * @param string $tableName The name of of the table where the index is
     * @param string|string[] $columns Column(s)
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropIndexByColumnsInstructions(string $tableName, string|array $columns): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropIndexByName(string $tableName, string $indexName): void
    {
        $instructions = $this->getDropIndexByNameInstructions($tableName, $indexName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the index specified by name from a database table.
     *
     * @param string $tableName The table name whe the index is
     * @param string $indexName The name of the index
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropIndexByNameInstructions(string $tableName, string $indexName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function addForeignKey(TableMetadata $table, ForeignKey $foreignKey): void
    {
        $instructions = $this->getAddForeignKeyInstructions($table, $foreignKey);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to adds the specified foreign key to a database table.
     *
     * @param \Migrations\Db\Table\Table $table The table to add the constraint to
     * @param \Migrations\Db\Table\ForeignKey $foreignKey The foreign key to add
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getAddForeignKeyInstructions(TableMetadata $table, ForeignKey $foreignKey): AlterInstructions;

    /**
     * @inheritDoc
     */
    public function dropForeignKey(string $tableName, array $columns, ?string $constraint = null): void
    {
        if ($constraint) {
            $instructions = $this->getDropForeignKeyInstructions($tableName, $constraint);
        } else {
            $instructions = $this->getDropForeignKeyByColumnsInstructions($tableName, $columns);
        }

        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string $tableName The table where the foreign key constraint is
     * @param string $constraint Constraint name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropForeignKeyInstructions(string $tableName, string $constraint): AlterInstructions;

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string $tableName The table where the foreign key constraint is
     * @param string[] $columns The list of column names
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropForeignKeyByColumnsInstructions(string $tableName, array $columns): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropTable(string $tableName): void
    {
        $instructions = $this->getDropTableInstructions($tableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified database table.
     *
     * @param string $tableName Table name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropTableInstructions(string $tableName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function renameTable(string $tableName, string $newName): void
    {
        $instructions = $this->getRenameTableInstructions($tableName, $newName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified database table.
     *
     * @param string $tableName Table name
     * @param string $newTableName New Name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getRenameTableInstructions(string $tableName, string $newTableName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function changePrimaryKey(TableMetadata $table, string|array|null $newColumns): void
    {
        $instructions = $this->getChangePrimaryKeyInstructions($table, $newColumns);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to change the primary key for the specified database table.
     *
     * @param \Migrations\Db\Table\Table $table Table
     * @param string|string[]|null $newColumns Column name(s) to belong to the primary key, or null to drop the key
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getChangePrimaryKeyInstructions(TableMetadata $table, string|array|null $newColumns): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function changeComment(TableMetadata $table, $newComment): void
    {
        $instructions = $this->getChangeCommentInstructions($table, $newComment);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instruction to change the comment for the specified database table.
     *
     * @param \Migrations\Db\Table\Table $table Table
     * @param string|null $newComment New comment string, or null to drop the comment
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getChangeCommentInstructions(TableMetadata $table, ?string $newComment): AlterInstructions;

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function executeActions(TableMetadata $table, array $actions): void
    {
        $instructions = new AlterInstructions();

        foreach ($actions as $action) {
            switch (true) {
                case $action instanceof AddColumn:
                    /** @var \Migrations\Db\Action\AddColumn $action */
                    $instructions->merge($this->getAddColumnInstructions($table, $action->getColumn()));
                    break;

                case $action instanceof AddIndex:
                    /** @var \Migrations\Db\Action\AddIndex $action */
                    $instructions->merge($this->getAddIndexInstructions($table, $action->getIndex()));
                    break;

                case $action instanceof AddForeignKey:
                    /** @var \Migrations\Db\Action\AddForeignKey $action */
                    $instructions->merge($this->getAddForeignKeyInstructions($table, $action->getForeignKey()));
                    break;

                case $action instanceof ChangeColumn:
                    /** @var \Migrations\Db\Action\ChangeColumn $action */
                    $instructions->merge($this->getChangeColumnInstructions(
                        $table->getName(),
                        $action->getColumnName(),
                        $action->getColumn()
                    ));
                    break;

                case $action instanceof DropForeignKey && !$action->getForeignKey()->getConstraint():
                    /** @var \Migrations\Db\Action\DropForeignKey $action */
                    $instructions->merge($this->getDropForeignKeyByColumnsInstructions(
                        $table->getName(),
                        $action->getForeignKey()->getColumns()
                    ));
                    break;

                case $action instanceof DropForeignKey && $action->getForeignKey()->getConstraint():
                    /** @var \Migrations\Db\Action\DropForeignKey $action */
                    $instructions->merge($this->getDropForeignKeyInstructions(
                        $table->getName(),
                        (string)$action->getForeignKey()->getConstraint()
                    ));
                    break;

                case $action instanceof DropIndex && $action->getIndex()->getName() !== null:
                    /** @var \Migrations\Db\Action\DropIndex $action */
                    $instructions->merge($this->getDropIndexByNameInstructions(
                        $table->getName(),
                        (string)$action->getIndex()->getName()
                    ));
                    break;

                case $action instanceof DropIndex && $action->getIndex()->getName() == null:
                    /** @var \Migrations\Db\Action\DropIndex $action */
                    $instructions->merge($this->getDropIndexByColumnsInstructions(
                        $table->getName(),
                        (array)$action->getIndex()->getColumns()
                    ));
                    break;

                case $action instanceof DropTable:
                    /** @var \Migrations\Db\Action\DropTable $action */
                    $instructions->merge($this->getDropTableInstructions(
                        $table->getName()
                    ));
                    break;

                case $action instanceof RemoveColumn:
                    /** @var \Migrations\Db\Action\RemoveColumn $action */
                    $instructions->merge($this->getDropColumnInstructions(
                        $table->getName(),
                        (string)$action->getColumn()->getName()
                    ));
                    break;

                case $action instanceof RenameColumn:
                    /** @var \Migrations\Db\Action\RenameColumn $action */
                    $instructions->merge($this->getRenameColumnInstructions(
                        $table->getName(),
                        (string)$action->getColumn()->getName(),
                        $action->getNewName()
                    ));
                    break;

                case $action instanceof RenameTable:
                    /** @var \Migrations\Db\Action\RenameTable $action */
                    $instructions->merge($this->getRenameTableInstructions(
                        $table->getName(),
                        $action->getNewName()
                    ));
                    break;

                case $action instanceof ChangePrimaryKey:
                    /** @var \Migrations\Db\Action\ChangePrimaryKey $action */
                    $instructions->merge($this->getChangePrimaryKeyInstructions(
                        $table,
                        $action->getNewColumns()
                    ));
                    break;

                case $action instanceof ChangeComment:
                    /** @var \Migrations\Db\Action\ChangeComment $action */
                    $instructions->merge($this->getChangeCommentInstructions(
                        $table,
                        $action->getNewComment()
                    ));
                    break;

                default:
                    throw new InvalidArgumentException(
                        sprintf("Don't know how to execute action: '%s'", get_class($action))
                    );
            }
        }

        $this->executeAlterSteps($table->getName(), $instructions);
    }
}
