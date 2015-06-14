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
namespace Migrations;

use Cake\Database\Connection;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Migration\MigrationInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Decorates an AdapterInterface in order to proxy some method to the actual
 * connection object.
 */
class CakeAdapter implements AdapterInterface
{

    /**
     * Decorated adapter
     *
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * Database connection
     *
     * @var \Cake\Database\Connection
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter The original adapter to decorate.
     * @param \Cake\Database\Connection $connection The connection to actually use.
     */
    public function __construct(AdapterInterface $adapter, Connection $connection)
    {
        $this->adapter = $adapter;
        $this->connection = $connection;
        $pdo = $adapter->getConnection();
        $connection->driver()->connection($pdo);
    }

    /**
     * Get all migrated version numbers.
     *
     * @return array
     */
    public function getVersions()
    {
        return $this->adapter->getVersions();
    }

    /**
     * Set adapter configuration options.
     *
     * @param  array $options
     * @return AdapterInterface
     */
    public function setOptions(array $options)
    {
        return $this->adapter->setOptions($options);
    }

    /**
     * Get all adapter options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->adapter->getOptions();
    }

    /**
     * Check if an option has been set.
     *
     * @param  string $name
     * @return boolean
     */
    public function hasOption($name)
    {
        return $this->adapter->hasOption($name);
    }

    /**
     * Get a single adapter option, or null if the option does not exist.
     *
     * @param  string $name
     * @return mixed
     */
    public function getOption($name)
    {
        return $this->adapter->getOption($name);
    }

    /**
     * Sets the console output.
     *
     * @param OutputInterface $output Output
     * @return AdapterInterface
     */
    public function setOutput(OutputInterface $output)
    {
        return $this->adapter->setOutput($output);
    }

    /**
     * Gets the console output.
     *
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->adapter->getOutput();
    }

    /**
     * Records a migration being run.
     *
     * @param MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param int $startTime Start Time
     * @param int $endTime End Time
     * @return AdapterInterface
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        return $this->adapter->migrated($migration, $direction, $startTime, $endTime);
    }

    /**
     * Does the schema table exist?
     *
     * @deprecated use hasTable instead.
     * @return boolean
     */
    public function hasSchemaTable()
    {
        return $this->adapter->hasSchemaTable();
    }

    /**
     * Creates the schema table.
     *
     * @return void
     */
    public function createSchemaTable()
    {
        return $this->adapter->createSchemaTable();
    }

    /**
     * Returns the adapter type.
     *
     * @return string
     */
    public function getAdapterType()
    {
        return $this->adapter->getAdapterType();
    }

    /**
     * Initializes the database connection.
     *
     * @throws \RuntimeException When the requested database driver is not installed.
     * @return void
     */
    public function connect()
    {
        return $this->adapter->connect();
    }

    /**
     * Closes the database connection.
     *
     * @return void
     */
    public function disconnect()
    {
        return $this->adapter->disconnect();
    }

    /**
     * Does the adapter support transactions?
     *
     * @return boolean
     */
    public function hasTransactions()
    {
        return $this->adapter->hasTransactions();
    }

    /**
     * Begin a transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->connection->begin();
    }

    /**
     * Commit a transaction.
     *
     * @return void
     */
    public function commitTransaction()
    {
        $this->connection->commit();
    }

    /**
     * Rollback a transaction.
     *
     * @return void
     */
    public function rollbackTransaction()
    {
        $this->connection->rollback();
    }

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql SQL
     * @return int
     */
    public function execute($sql)
    {
        return $this->adapter->execute($sql);
    }

    /**
     * Executes a SQL statement and returns the result as an array.
     *
     * @param string $sql SQL
     * @return array
     */
    public function query($sql)
    {
        return $this->adapter->query($sql);
    }

    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchRow($sql)
    {
        return $this->adapter->fetchRow($sql);
    }

    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchAll($sql)
    {
        return $this->adapter->fetchAll($sql);
    }

    /**
     * Inserts data into the table
     *
     * @param Table $table where to insert data
     * @param array $columns column names
     * @param $data
     */
    public function insert(Table $table, $columns, $data)
    {
        return $this->adapter->insert($table, $columns, $data);
    }

    /**
     * Quotes a table name for use in a query.
     *
     * @param string $tableName Table Name
     * @return string
     */
    public function quoteTableName($tableName)
    {
        return $this->adapter->quoteTableName($tableName);
    }

    /**
     * Quotes a column name for use in a query.
     *
     * @param string $columnName Table Name
     * @return string
     */
    public function quoteColumnName($columnName)
    {
        return $this->adapter->quoteColumnName($columnName);
    }

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table Name
     * @return boolean
     */
    public function hasTable($tableName)
    {
        return $this->adapter->hasTable($tableName);
    }

    /**
     * Creates the specified database table.
     *
     * @param Table $table Table
     * @return void
     */
    public function createTable(Table $table)
    {
        return $this->adapter->createTable($table);
    }

    /**
     * Renames the specified database table.
     *
     * @param string $tableName Table Name
     * @param string $newName   New Name
     * @return void
     */
    public function renameTable($tableName, $newName)
    {
        return $this->adapter->renameTable($tableName, $newName);
    }

    /**
     * Drops the specified database table.
     *
     * @param string $tableName Table Name
     * @return void
     */
    public function dropTable($tableName)
    {
        return $this->adapter->dropTable($tableName);
    }

    /**
     * Returns table columns
     *
     * @param string $tableName Table Name
     * @return Column[]
     */
    public function getColumns($tableName)
    {
        return $this->adapter->getColumns($tableName);
    }

    /**
     * Checks to see if a column exists.
     *
     * @param string $tableName  Table Name
     * @param string $columnName Column Name
     * @return boolean
     */
    public function hasColumn($tableName, $columnName)
    {
        return $this->adapter->hasColumn($tableName, $columnName);
    }

    /**
     * Adds the specified column to a database table.
     *
     * @param Table  $table  Table
     * @param Column $column Column
     * @return void
     */
    public function addColumn(Table $table, Column $column)
    {
        return $this->adapter->addColumn($table, $column);
    }

    /**
     * Renames the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     * @return void
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        return $this->adapter->renameColumn($tableName, $columnName, $newColumnName);
    }

    /**
     * Change a table column type.
     *
     * @param string $tableName  Table Name
     * @param string $columnName Column Name
     * @param Column $newColumn  New Column
     * @return Table
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        return $this->adapter->changeColumn($tableName, $columnName, $newColumn);
    }

    /**
     * Drops the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @return void
     */
    public function dropColumn($tableName, $columnName)
    {
        return $this->adapter->dropColumn($tableName, $columnName);
    }

    /**
     * Checks to see if an index exists.
     *
     * @param string $tableName Table Name
     * @param mixed  $columns   Column(s)
     * @return boolean
     */
    public function hasIndex($tableName, $columns)
    {
        return $this->adapter->hasIndex($tableName, $columns);
    }

    /**
     * Adds the specified index to a database table.
     *
     * @param Table $table Table
     * @param Index $index Index
     * @return void
     */
    public function addIndex(Table $table, Index $index)
    {
        return $this->adapter->addIndex($table, $index);
    }

    /**
     * Drops the specified index from a database table.
     *
     * @param string $tableName
     * @param mixed  $columns Column(s)
     * @return void
     */
    public function dropIndex($tableName, $columns)
    {
        return $this->adapter->dropIndex($tableName, $columns);
    }

    /**
     * Drops the index specified by name from a database table.
     *
     * @param string $tableName
     * @param string $indexName
     * @return void
     */
    public function dropIndexByName($tableName, $indexName)
    {
        return $this->adapter->dropIndexByName($tableName, $indexName);
    }

    /**
     * Checks to see if a foreign key exists.
     *
     * @param string   $tableName
     * @param string[] $columns    Column(s)
     * @param string   $constraint Constraint name
     * @return boolean
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        return $this->adapter->hasForeignKey($tableName, $columns, $constraint);
    }

    /**
     * Adds the specified foreign key to a database table.
     *
     * @param Table      $table
     * @param ForeignKey $foreignKey
     * @return void
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        return $this->adapter->addForeignKey($table, $foreignKey);
    }

    /**
     * Drops the specified foreign key from a database table.
     *
     * @param string   $tableName
     * @param string[] $columns    Column(s)
     * @param string   $constraint Constraint name
     * @return void
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        return $this->adapter->dropForeignKey($tableName, $columns, $constraint);
    }

    /**
     * Returns an array of the supported Phinx column types.
     *
     * @return array
     */
    public function getColumnTypes()
    {
        return $this->adapter->getColumnTypes();
    }

    /**
     * Checks that the given column is of a supported type.
     *
     * @param  Column $column
     * @return boolean
     */
    public function isValidColumnType(Column $column)
    {
        return $this->adapter->isValidColumnType($column);
    }

    /**
     * Converts the Phinx logical type to the adapter's SQL type.
     *
     * @param string $type
     * @param integer $limit
     * @return string
     */
    public function getSqlType($type, $limit = null)
    {
        return $this->adapter->getSqlType($type, $limit);
    }

    /**
     * Creates a new database.
     *
     * @param string $name Database Name
     * @param array $options Options
     * @return void
     */
    public function createDatabase($name, $options = array())
    {
        return $this->adapter->createDatabase($name, $options);
    }

    /**
     * Checks to see if a database exists.
     *
     * @param string $name Database Name
     * @return boolean
     */
    public function hasDatabase($name)
    {
        return $this->adapter->hasDatabase($name);
    }

    /**
     * Drops the specified database.
     *
     * @param string $name Database Name
     * @return void
     */
    public function dropDatabase($name)
    {
        return $this->adapter->dropDatabase($name);
    }
}
