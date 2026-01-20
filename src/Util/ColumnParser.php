<?php
declare(strict_types=1);

namespace Migrations\Util;

use Cake\Collection\Collection;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Migrations\Db\Adapter\AdapterInterface;
use ReflectionClass;

/**
 * Utility class used to parse arguments passed to a ``bake migration`` class
 */
class ColumnParser
{
    /**
     * Regex used to parse the column definition passed through the shell
     *
     * @var string
     */
    protected string $regexpParseColumn = '/
        ^
        (\w+)
        (?::(\w+\??
            (?:\[
                (?:[0-9]|[1-9][0-9]+)
                (?:,(?:[0-9]|[1-9][0-9]+))?
            \])?
        ))?
        (?::default\[([^\]]+)\])?
        (?::(\w+))?
        (?::(\w+))?
        $
        /x';

    /**
     * Regex used to parse the field type and length
     *
     * @var string
     */
    protected string $regexpParseField = '/(\w+\??)\[([0-9,]+)\]/';

    /**
     * Parses a list of arguments into an array of fields
     *
     * @param array<int, string> $arguments A list of arguments being parsed
     * @return array<string, array>
     */
    public function parseFields(array $arguments): array
    {
        $fields = [];
        $arguments = $this->validArguments($arguments);
        foreach ($arguments as $field) {
            preg_match($this->regexpParseColumn, $field, $matches);
            $field = $matches[1];
            $type = Hash::get($matches, 2, '');
            $defaultValue = Hash::get($matches, 3);
            $indexType = Hash::get($matches, 4);

            $typeIsPk = in_array($type, ['primary', 'primary_key'], true);
            $isPrimaryKey = false;
            if ($typeIsPk || in_array($indexType, ['primary', 'primary_key'], true)) {
                $isPrimaryKey = true;

                if ($typeIsPk) {
                    $type = 'primary';
                }
            }

            // Handle references - convert to integer type
            $isReference = in_array($type, ['references', 'references?'], true);
            if ($isReference) {
                $type = str_contains($type, '?') ? 'integer?' : 'integer';
            }

            $nullable = str_contains($type, '?');
            $type = $nullable ? str_replace('?', '', $type) : $type;

            [$type, $length] = $this->getTypeAndLength($field, $type);
            $fields[$field] = [
                'columnType' => $type,
                'options' => [
                    'null' => $nullable,
                    'default' => $this->parseDefaultValue($defaultValue, $type ?? 'string'),
                ],
            ];

            if ($length !== null) {
                if (is_array($length)) {
                    [$fields[$field]['options']['precision'], $fields[$field]['options']['scale']] = $length;
                } else {
                    $fields[$field]['options']['limit'] = $length;
                }
            }

            if ($isPrimaryKey === true && $type === 'integer') {
                $fields[$field]['options']['autoIncrement'] = true;
            }
        }

        return $fields;
    }

    /**
     * Parses a list of arguments into an array of indexes
     *
     * @param array<int, string> $arguments A list of arguments being parsed
     * @return array<string, array>
     */
    public function parseIndexes(array $arguments): array
    {
        $indexes = [];
        $arguments = $this->validArguments($arguments);
        foreach ($arguments as $field) {
            preg_match($this->regexpParseColumn, $field, $matches);
            $field = $matches[1];
            $type = Hash::get($matches, 2);
            $indexType = Hash::get($matches, 4);
            $indexName = Hash::get($matches, 5);

            // Skip references - they create foreign keys, not indexes
            if ($type && str_starts_with($type, 'references')) {
                continue;
            }

            if (
                in_array($type, ['primary', 'primary_key'], true) ||
                in_array($indexType, ['primary', 'primary_key'], true) ||
                $indexType === null
            ) {
                continue;
            }

            $indexUnique = false;
            if ($indexType === 'unique') {
                $indexUnique = true;
            }

            $indexName = $this->getIndexName($field, $indexType, $indexName, $indexUnique);

            if (empty($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'options' => [
                        'unique' => $indexUnique,
                        'name' => $indexName,
                    ],
                ];
            }

            $indexes[$indexName]['columns'][] = $field;
        }

        return $indexes;
    }

    /**
     * Parses a list of arguments into an array of fields composing the primary key
     * of the table
     *
     * @param array<int, string> $arguments A list of arguments being parsed
     * @return array<string>
     */
    public function parsePrimaryKey(array $arguments): array
    {
        $primaryKey = [];
        $arguments = $this->validArguments($arguments);
        foreach ($arguments as $field) {
            preg_match($this->regexpParseColumn, $field, $matches);
            $field = $matches[1];
            $type = Hash::get($matches, 2);
            $indexType = Hash::get($matches, 4);

            if (
                in_array($type, ['primary', 'primary_key'], true)
                || in_array($indexType, ['primary', 'primary_key'], true)
            ) {
                $primaryKey[] = $field;
            }
        }

        return $primaryKey;
    }

    /**
     * Parses a list of arguments into an array of foreign key constraints
     *
     * @param array<int, string> $arguments A list of arguments being parsed
     * @return array<string, array>
     */
    public function parseForeignKeys(array $arguments): array
    {
        $foreignKeys = [];
        $arguments = $this->validArguments($arguments);

        foreach ($arguments as $field) {
            preg_match($this->regexpParseColumn, $field, $matches);
            $fieldName = $matches[1];
            $type = Hash::get($matches, 2, '');
            $indexType = Hash::get($matches, 4);
            $indexName = Hash::get($matches, 5);

            // Check if type is 'references' or 'references?'
            $isReference = str_starts_with($type, 'references');
            if (!$isReference) {
                continue;
            }

            // Determine referenced table
            // If indexType is provided, use it as the referenced table name
            // Otherwise, infer from field name (e.g., category_id -> categories)
            $referencedTable = $indexType;
            if (!$referencedTable) {
                // Remove common suffixes like _id and pluralize
                $referencedTable = preg_replace('/_id$/', '', $fieldName);
                $referencedTable = Inflector::pluralize($referencedTable);
            }

            // Generate constraint name
            $constraintName = $indexName ?: 'fk_' . $fieldName;

            $foreignKeys[$constraintName] = [
                'type' => 'foreign',
                'columns' => [$fieldName],
                'references' => [$referencedTable, 'id'],
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ];
        }

        return $foreignKeys;
    }

    /**
     * Returns a list of only valid arguments
     *
     * @param array<string> $arguments A list of arguments
     * @return array<string>
     */
    public function validArguments(array $arguments): array
    {
        $collection = new Collection($arguments);

        return $collection->filter(function ($value, $field) {
            return preg_match($this->regexpParseColumn, (string)$field);
        })->toArray();
    }

    /**
     * Get the type and length of a field based on the field and the type passed
     *
     * @param string $field Name of field
     * @param string|null $type User-specified type
     * @return array{0: string|null, 1: int|array<int>|null} First value is the field type, second value is the field length. If no length
     * can be extracted, null is returned for the second value
     */
    public function getTypeAndLength(string $field, ?string $type): array
    {
        if ($type && preg_match($this->regexpParseField, $type, $matches)) {
            $length = $matches[2];
            if (str_contains($length, ',')) {
                $length = array_map('intval', explode(',', $length));
            } else {
                $length = (int)$length;
            }

            return [$matches[1], $length];
        }

        /** @var string $fieldType */
        $fieldType = $this->getType($field, $type);
        $length = $this->getLength($fieldType);

        return [$fieldType, $length];
    }

    /**
     * Retrieves a type that should be used for a specific field
     *
     * @param string $field Name of field
     * @param string|null $type User-specified type
     * @return string|null
     */
    public function getType(string $field, ?string $type): ?string
    {
        $reflector = new ReflectionClass(AdapterInterface::class);
        $collection = new Collection($reflector->getConstants());

        $validTypes = $collection->filter(function ($value, $constant) {
            return substr($constant, 0, strlen('TYPE_')) === 'TYPE_' ||
                   substr($constant, 0, strlen('PHINX_TYPE_')) === 'PHINX_TYPE_';
        })->toArray();
        $fieldType = $type;
        if ($type === null || !in_array($type, $validTypes, true)) {
            if ($type === 'primary') {
                $fieldType = 'integer';
            } elseif ($field === 'id') {
                $fieldType = 'integer';
            } elseif (in_array($field, ['created', 'modified', 'updated'], true) || substr($field, -3) === '_at') {
                $fieldType = 'datetime';
            } elseif (in_array($field, ['latitude', 'longitude', 'lat', 'lng'], true)) {
                $fieldType = 'decimal';
            } else {
                $fieldType = 'string';
            }
        }

        return $fieldType;
    }

    /**
     * Returns the default length to be used for a given type.
     *
     * @param string $type User-specified type
     * @return int|int[]|null
     */
    public function getLength(string $type): int|array|null
    {
        $length = null;
        if ($type === 'string') {
            $length = 255;
        } elseif ($type === 'tinyinteger') {
            $length = 4;
        } elseif ($type === 'smallinteger') {
            $length = 6;
        } elseif ($type === 'integer') {
            $length = 11;
        } elseif ($type === 'biginteger') {
            $length = 20;
        } elseif ($type === 'decimal') {
            $length = [10, 6];
        }

        return $length;
    }

    /**
     * Returns the default length to be used for a given fie
     *
     * @param string $field Name of field
     * @param string|null $indexType Type of index
     * @param string|null $indexName Name of index
     * @param bool $indexUnique Whether this is a unique index or not
     * @return string
     */
    public function getIndexName(string $field, ?string $indexType, ?string $indexName, bool $indexUnique): string
    {
        if (!$indexName) {
            $indexName = strtoupper('BY_' . $field);
            if ($indexType === 'primary') {
                $indexName = 'PRIMARY';
            } elseif ($indexUnique) {
                $indexName = strtoupper('UNIQUE_' . $field);
            }
        }

        return $indexName;
    }

    /**
     * Parses a default value string into the appropriate PHP type.
     *
     * Supports:
     * - Booleans: true, false
     * - Null: null, NULL
     * - Integers: 123, -123
     * - Floats: 1.5, -1.5
     * - Strings: 'hello' (quoted) or unquoted values
     *
     * @param string|null $value The raw default value from the command line
     * @param string $columnType The column type to help with type coercion
     * @return string|int|float|bool|null The parsed default value
     */
    public function parseDefaultValue(?string $value, string $columnType): string|int|float|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $lowerValue = strtolower($value);

        // Handle null
        if ($lowerValue === 'null') {
            return null;
        }

        // Handle booleans
        if ($lowerValue === 'true') {
            return true;
        }
        if ($lowerValue === 'false') {
            return false;
        }

        // Handle quoted strings - strip quotes
        if (
            (str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
        ) {
            return substr($value, 1, -1);
        }

        // Handle integers
        if (preg_match('/^-?[0-9]+$/', $value)) {
            return (int)$value;
        }

        // Handle floats
        if (preg_match('/^-?[0-9]+\.[0-9]+$/', $value)) {
            return (float)$value;
        }

        // Return as-is for SQL expressions like CURRENT_TIMESTAMP
        return $value;
    }
}
