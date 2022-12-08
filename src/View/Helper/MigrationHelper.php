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
namespace Migrations\View\Helper;

use Cake\Database\Schema\TableSchema;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use Cake\View\View;

/**
 * Migration Helper class for output of field data in migration files.
 *
 * MigrationHelper encloses all methods needed while working with HTML pages.
 */
class MigrationHelper extends Helper
{
    /**
     * Schemas list for tables analyzed during migration baking
     *
     * @var array
     */
    protected $schemas = [];

    /**
     * Stores the ``$this->table()`` statements issued while baking.
     * It helps prevent duplicate calls in case of complex conditions
     *
     * @var array
     */
    public $tableStatements = [];

    /**
     * @var array
     */
    public $returnedData = [];

    /**
     * Store a table's column listing.
     *
     * @param string $table The table name
     * @param string $columnsList The column list to store.
     * @return void
     */
    public function storeReturnedData(string $table, string $columnsList): void
    {
        $this->returnedData['dropForeignKeys'][$table][] = $columnsList;
    }

    /**
     * Get all stored data.
     *
     * @return array An array of stored data.
     */
    public function getReturnedData(): array
    {
        return $this->returnedData;
    }

    /**
     * Constructor
     *
     * ### Settings
     *
     * - `collection` \Cake\Database\Schema\Collection
     *
     * @param \Cake\View\View $View The View this helper is being attached to.
     * @param array $config Configuration settings for the helper.
     */
    public function __construct(View $View, array $config = [])
    {
        parent::__construct($View, $config);
    }

    /**
     * Returns the method to be used for the Table::save()
     *
     * @param string $action Name of action to take against the table
     * @return string
     */
    public function tableMethod($action)
    {
        if ($action === 'drop_table') {
            return 'drop';
        }

        if ($action === 'create_table') {
            return 'create';
        }

        return 'update';
    }

    /**
     * Returns the method to be used for the index manipulation
     *
     * @param string $action Name of action to take against the table
     * @return string
     */
    public function indexMethod($action)
    {
        if ($action === 'drop_field') {
            return 'removeIndex';
        }

        return 'addIndex';
    }

    /**
     * Returns the method to be used for the column manipulation
     *
     * @param string $action Name of action to take against the table
     * @return string
     */
    public function columnMethod($action)
    {
        if ($action === 'drop_field') {
            return 'removeColumn';
        }

        if ($action === 'alter_field') {
            return 'changeColumn';
        }

        return 'addColumn';
    }

    /**
     * Returns the Cake\Database\Schema\TableSchema for $table
     *
     * @param string|\Cake\Database\Schema\TableSchema $table Name of the table to retrieve constraints for
     *  or a table schema object.
     * @return \Cake\Database\Schema\TableSchema
     */
    protected function schema($table)
    {
        if (is_string($table) && isset($this->schemas[$table])) {
            return $this->schemas[$table];
        }

        if ($table instanceof TableSchema) {
            return $this->schemas[$table->name()] = $table;
        }

        $collection = $this->getConfig('collection');
        $schema = $collection->describe($table);
        $this->schemas[$table] = $schema;

        return $schema;
    }

    /**
     * Returns an array of column data for a given table
     *
     * @param string|\Cake\Database\Schema\TableSchema $table Name of the table to retrieve constraints for
     *  or a table schema object.
     * @return array
     */
    public function columns($table)
    {
        $tableSchema = $table;
        if (!($tableSchema instanceof TableSchema)) {
            $tableSchema = $this->schema($tableSchema);
        }
        $columns = [];
        $tablePrimaryKeys = $tableSchema->getPrimaryKey();
        foreach ($tableSchema->columns() as $column) {
            if (in_array($column, $tablePrimaryKeys, true)) {
                continue;
            }
            $columns[$column] = $this->column($tableSchema, $column);
        }

        return $columns;
    }

    /**
     * Returns an array of indexes for a given table
     *
     * @param string|\Cake\Database\Schema\TableSchema $table Name of the table to retrieve constraints for
     *  or a table schema object.
     * @return array
     */
    public function indexes($table)
    {
        $tableSchema = $table;
        if (!($tableSchema instanceof TableSchema)) {
            $tableSchema = $this->schema($tableSchema);
        }

        $tableIndexes = $tableSchema->indexes();
        $indexes = [];
        if (!empty($tableIndexes)) {
            foreach ($tableIndexes as $name) {
                $indexes[$name] = $tableSchema->getIndex($name);
            }
        }

        return $indexes;
    }

    /**
     * Returns an array of constraints for a given table
     *
     * @param string|\Cake\Database\Schema\TableSchema $table Name of the table to retrieve constraints for
     *  or a table schema object.
     * @return array
     */
    public function constraints($table)
    {
        $tableSchema = $table;
        if (!($tableSchema instanceof TableSchema)) {
            $tableSchema = $this->schema($tableSchema);
        }

        $constraints = [];
        $tableConstraints = $tableSchema->constraints();
        if (empty($tableConstraints)) {
            return $constraints;
        }

        if ($tableConstraints[0] === 'primary') {
            unset($tableConstraints[0]);
        }
        if (!empty($tableConstraints)) {
            foreach ($tableConstraints as $name) {
                $constraint = $tableSchema->getConstraint($name);
                if ($constraint && isset($constraint['update'])) {
                    $constraint['update'] = $this->formatConstraintAction($constraint['update']);
                }
                if ($constraint && isset($constraint['delete'])) {
                    $constraint['delete'] = $this->formatConstraintAction($constraint['delete']);
                }
                $constraints[$name] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * Format a constraint action if it is not already in the format expected by Phinx
     *
     * @param string $constraint Constraint action name
     * @return string Constraint action name altered if needed.
     */
    public function formatConstraintAction($constraint)
    {
        if (defined('\Phinx\Db\Table\ForeignKey::' . $constraint)) {
            return $constraint;
        }

        return strtoupper(Inflector::underscore($constraint));
    }

    /**
     * Returns the primary key data for a given table
     *
     * @param string|\Cake\Database\Schema\TableSchema $table Name of the table ot retrieve primary key for
     * @return array
     */
    public function primaryKeys($table)
    {
        $tableSchema = $table;
        if (!($tableSchema instanceof TableSchema)) {
            $tableSchema = $this->schema($tableSchema);
        }
        $primaryKeys = [];
        $tablePrimaryKeys = $tableSchema->getPrimaryKey();
        foreach ($tableSchema->columns() as $column) {
            if (in_array($column, $tablePrimaryKeys, true)) {
                $primaryKeys[] = ['name' => $column, 'info' => $this->column($tableSchema, $column)];
            }
        }

        return $primaryKeys;
    }

    /**
     * Returns whether the $tables list given as arguments contains primary keys
     * unsigned.
     *
     * @param array $tables List of tables to check
     * @return bool
     */
    public function hasUnsignedPrimaryKey(array $tables)
    {
        foreach ($tables as $table) {
            $tableSchema = $table;
            if (!($table instanceof TableSchema)) {
                $tableSchema = $this->schema($table);
            }
            $tablePrimaryKeys = $tableSchema->getPrimaryKey();

            foreach ($tablePrimaryKeys as $primaryKey) {
                $column = $tableSchema->getColumn($primaryKey);
                if (isset($column['unsigned']) && $column['unsigned'] === true) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the primary key columns name for a given table
     *
     * @param string $table Name of the table ot retrieve primary key for
     * @return array
     */
    public function primaryKeysColumnsList($table)
    {
        $primaryKeys = $this->primaryKeys($table);
        /** @var array $primaryKeysColumns */
        $primaryKeysColumns = Hash::extract($primaryKeys, '{n}.name');
        sort($primaryKeysColumns);

        return $primaryKeysColumns;
    }

    /**
     * Returns an array of column data for a single column
     *
     * @param \Cake\Database\Schema\TableSchema $tableSchema Name of the table to retrieve columns for
     * @param string $column A column to retrieve data for
     * @return array
     */
    public function column($tableSchema, $column)
    {
        $columnType = $tableSchema->getColumnType($column);
        // Phinx doesn't understand timestampfractional.
        if ($columnType === 'timestampfractional') {
            $columnType = 'timestamp';
        }

        return [
            'columnType' => $columnType,
            'options' => $this->attributes($tableSchema, $column),
        ];
    }

    /**
     * Compute the final array of options to display in a `addColumn` or `changeColumn` instruction.
     * The method also takes care of translating properties names between CakePHP database layer and phinx database
     * layer.
     *
     * @param array $options Array of options to compute the final list from.
     * @return array
     */
    public function getColumnOption(array $options)
    {
        $wantedOptions = array_flip([
            'length',
            'limit',
            'default',
            'signed',
            'null',
            'comment',
            'autoIncrement',
            'precision',
            'after',
        ]);
        $columnOptions = array_intersect_key($options, $wantedOptions);
        if (empty($columnOptions['comment'])) {
            unset($columnOptions['comment']);
        }
        if (empty($columnOptions['autoIncrement'])) {
            unset($columnOptions['autoIncrement']);
        }
        if (isset($columnOptions['signed']) && $columnOptions['signed'] === true) {
            unset($columnOptions['signed']);
        }
        if ($columnOptions['precision'] === null) {
            unset($columnOptions['precision']);
        } else {
            // due to Phinx using different naming for the precision and scale to CakePHP
            $columnOptions['scale'] = $columnOptions['precision'];

            if (isset($columnOptions['limit'])) {
                $columnOptions['precision'] = $columnOptions['limit'];
                unset($columnOptions['limit']);
            }
            if (isset($columnOptions['length'])) {
                $columnOptions['precision'] = $columnOptions['length'];
                unset($columnOptions['length']);
            }
        }

        return $columnOptions;
    }

    /**
     * Returns a string-like representation of a value
     *
     * @param string|int|bool|null $value A value to represent as a string
     * @param bool $numbersAsString Set tu true to return as string.
     * @return mixed
     */
    public function value($value, $numbersAsString = false)
    {
        if ($value === null || $value === 'null' || $value === 'NULL') {
            return 'null';
        }

        if ($value === 'true' || $value === 'false') {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (!$numbersAsString && (is_numeric($value) || ctype_digit($value))) {
            return (float)$value;
        }

        return sprintf("'%s'", addslashes((string)$value));
    }

    /**
     * Returns an array of attributes for a given table column
     *
     * @param \Cake\Database\Schema\TableSchema|string $table Name of the table to retrieve columns for
     * @param string $column A column to retrieve attributes for
     * @return array
     */
    public function attributes($table, $column)
    {
        $tableSchema = $table;
        if (!($tableSchema instanceof TableSchema)) {
            $tableSchema = $this->schema($table);
        }
        $validOptions = [
            'length', 'limit',
            'default', 'null',
            'precision', 'scale',
            'after', 'update',
            'comment', 'unsigned',
            'signed', 'properties',
            'autoIncrement', 'unique',
        ];

        $attributes = [];
        $options = $tableSchema->getColumn($column);
        if ($options === null) {
            return [];
        }

        foreach ($options as $_option => $value) {
            $option = $_option;
            switch ($_option) {
                case 'length':
                    $option = 'limit';
                    break;
                case 'unsigned':
                    $option = 'signed';
                    $value = !$value;
                    break;
                case 'unique':
                    $value = (bool)$value;
                    break;
            }

            if (!in_array($option, $validOptions, true)) {
                continue;
            }

            if ($option === 'default' && is_string($value)) {
                $value = trim($value, "'");
            }

            $attributes[$option] = $value;
        }

        ksort($attributes);

        return $attributes;
    }

    /**
     * Returns an array converted into a formatted multiline string
     *
     * @param array $list array of items to be stringified
     * @param array $options options to use
     * @param array $wantedOptions The options you want to include in the output. If undefined all keys are included.
     * @return string
     */
    public function stringifyList(array $list, array $options = [], array $wantedOptions = [])
    {
        if (!empty($wantedOptions)) {
            $list = array_intersect_key($list, $wantedOptions);
            if (empty($list['comment'])) {
                unset($list['comment']);
            }
        }

        $options += [
            'indent' => 2,
        ];

        if (!empty($options['remove'])) {
            foreach ($options['remove'] as $option) {
                unset($list[$option]);
            }
            unset($options['remove']);
        }

        if (!$list) {
            return '';
        }

        ksort($list);
        foreach ($list as $k => &$v) {
            if (is_array($v)) {
                $v = $this->stringifyList($v, [
                    'indent' => $options['indent'] + 1,
                ]);
                $v = sprintf('[%s]', $v);
            } else {
                $v = $this->value($v, $k === 'default');
            }
            if (!is_numeric($k)) {
                $v = "'$k' => $v";
            }
        }

        $start = $end = '';
        $join = ', ';
        if ($options['indent']) {
            $join = ',';
            $start = "\n" . str_repeat('    ', $options['indent']);
            $join .= $start;
            $end = "\n" . str_repeat('    ', $options['indent'] - 1);
        }

        return $start . implode($join, $list) . ',' . $end;
    }

    /**
     * Returns a $this->table() statement only if it was not issued already
     *
     * @param string $table Table for which the statement is needed
     * @param bool $reset Reset previously set statement.
     * @return string
     */
    public function tableStatement($table, $reset = false)
    {
        if ($reset === true) {
            unset($this->tableStatements[$table]);
        }

        if (!isset($this->tableStatements[$table])) {
            $this->tableStatements[$table] = true;

            return '$this->table(\'' . $table . '\')';
        }

        return '';
    }

    /**
     * Get the stored table statement.
     *
     * @param string $table The table name.
     * @return bool|string|null
     */
    public function getTableStatement(string $table)
    {
        if (array_key_exists($table, $this->tableStatements)) {
            return $this->tableStatements[$table];
        }

        return null;
    }

    /**
     * Remove a stored table statement
     *
     * @param string $table The table to remove
     * @return void
     */
    public function removeTableStatement(string $table): void
    {
        unset($this->tableStatements[$table]);
    }

    /**
     * Render an element.
     *
     * @param string $name The name of the element to render.
     * @param array $data Additional data for the element.
     * @return ?string
     */
    public function element(string $name, array $data): ?string
    {
        return $this->getView()->element($name, $data);
    }

    /**
     * Wrapper around Hash::extract()
     *
     * @param array|\ArrayAccess $list The data to extract from.
     * @param string $path The path to extract.
     * @return mixed
     */
    public function extract($list, string $path = '{n}.name')
    {
        return Hash::extract($list, $path);
    }

    /**
     * Get data to use in create tables element
     *
     * @param string|\Cake\Database\Schema\TableSchema $table Name of the table to retrieve constraints for
     *  or a table schema object.
     * @return array
     */
    public function getCreateTableData($table): array
    {
        $constraints = $this->constraints($table);
        $indexes = $this->indexes($table);
        $foreignKeys = [];
        foreach ($constraints as $constraint) {
            if ($constraint['type'] === 'foreign') {
                $foreignKeys[] = $constraint['columns'];
            }
        }
        $indexes = array_filter($indexes, function ($index) use ($foreignKeys) {
            return !in_array($index['columns'], $foreignKeys, true);
        });
        $result = compact('constraints', 'indexes', 'foreignKeys');

        return $result;
    }

    /**
     * Get data to use inside the create-tables element
     *
     * @param array $tables The tables to create element data for.
     * @return array
     */
    public function getCreateTablesElementData(array $tables)
    {
        $result = [
            'constraints' => [],
            'tables' => [],
        ];
        foreach ($tables as $table) {
            $tableName = $table;
            if ($table instanceof TableSchema) {
                $tableName = $table->name();
            }
            $data = $this->getCreateTableData($table);
            $tableConstraintsNoUnique = array_filter(
                $data['constraints'],
                function ($constraint) {
                    return $constraint['type'] !== 'unique';
                }
            );
            if ($tableConstraintsNoUnique) {
                $result['constraints'][$tableName] = $data['constraints'];
            }
            $result['tables'][$tableName] = $data;
        }

        return $result;
    }
}
