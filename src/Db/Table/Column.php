<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Schema\Column as DatabaseColumn;
use Cake\Database\Schema\TableSchemaInterface;
use Migrations\Db\Adapter\PostgresAdapter;
use Migrations\Db\Literal;
use RuntimeException;

/**
 * This object is based loosely on: https://api.rubyonrails.org/classes/ActiveRecord/ConnectionAdapters/Table.html.
 *
 * ## Configuration
 *
 * The following configuration options can be set in your application's config:
 *
 * - `Migrations.unsigned_primary_keys` (bool): When true, identity columns default to unsigned.
 *   Default: false
 *
 * - `Migrations.unsigned_ints` (bool): When true, all integer columns default to unsigned.
 *   Default: false
 *
 * Example configuration in config/app.php:
 * ```php
 * 'Migrations' => [
 *     'unsigned_primary_keys' => true,
 *     'unsigned_ints' => true,
 * ]
 * ```
 *
 * Note: Explicitly calling setUnsigned() or setSigned() on a column will override these defaults.
 */
class Column extends DatabaseColumn
{
    public const BIGINTEGER = TableSchemaInterface::TYPE_BIGINTEGER;
    public const SMALLINTEGER = TableSchemaInterface::TYPE_SMALLINTEGER;
    public const TINYINTEGER = TableSchemaInterface::TYPE_TINYINTEGER;
    public const BINARY = TableSchemaInterface::TYPE_BINARY;
    public const BOOLEAN = TableSchemaInterface::TYPE_BOOLEAN;
    public const CHAR = TableSchemaInterface::TYPE_CHAR;
    public const DATE = TableSchemaInterface::TYPE_DATE;
    public const DATETIME = TableSchemaInterface::TYPE_DATETIME;
    public const DECIMAL = TableSchemaInterface::TYPE_DECIMAL;
    public const FLOAT = TableSchemaInterface::TYPE_FLOAT;
    public const INTEGER = TableSchemaInterface::TYPE_INTEGER;
    public const STRING = TableSchemaInterface::TYPE_STRING;
    public const TEXT = TableSchemaInterface::TYPE_TEXT;
    public const TIME = TableSchemaInterface::TYPE_TIME;
    public const TIMESTAMP = TableSchemaInterface::TYPE_TIMESTAMP;
    public const UUID = TableSchemaInterface::TYPE_UUID;
    public const BINARYUUID = TableSchemaInterface::TYPE_BINARY_UUID;
    public const NATIVEUUID = TableSchemaInterface::TYPE_NATIVE_UUID;
    /** MySQL-only column type */
    public const YEAR = TableSchemaInterface::TYPE_YEAR;
    /** MySQL/Postgres-only column type */
    public const JSON = TableSchemaInterface::TYPE_JSON;
    /** Postgres-only column type */
    public const CIDR = TableSchemaInterface::TYPE_CIDR;
    /** Postgres-only column type */
    public const INET = TableSchemaInterface::TYPE_INET;
    /** Postgres-only column type */
    public const MACADDR = TableSchemaInterface::TYPE_MACADDR;
    /** Postgres-only column type */
    public const INTERVAL = TableSchemaInterface::TYPE_INTERVAL;

    /**
     * @var int|null
     */
    protected ?int $seed = null;

    /**
     * @var int|null
     */
    protected ?int $scale = null;

    /**
     * @var string|null
     */
    protected ?string $update = null;

    /**
     * @var bool
     */
    protected bool $timezone = false;

    /**
     * @var array
     */
    protected array $properties = [];

    /**
     * @var string|null
     */
    protected ?string $collation = null;

    /**
     * @var array|null
     */
    protected ?array $values = null;

    /**
     * @var string|null
     */
    protected ?string $algorithm = null;

    /**
     * @var string|null
     */
    protected ?string $lock = null;

    /**
     * @var bool|null
     */
    protected ?bool $fixed = null;

    /**
     * Column constructor
     *
     * @param string $name The name of the column.
     * @param string $type The type of the column.
     * @param bool|null $null Whether the column allows nulls.
     * @param mixed $default The default value for the column.
     * @param int|null $length The length of the column.
     * @param bool $identity Whether the column is an identity column.
     * @param string|null $generated Postgres-only generated option for identity columns (always|default).
     * @param int|null $precision The precision for decimal columns.
     * @param int|null $increment The increment for identity columns.
     * @param string|null $after The column to add this column after.
     * @param string|null $onUpdate The ON UPDATE function for the column.
     * @param string|null $comment The comment for the column.
     * @param bool|null $unsigned Whether the column is unsigned.
     * @param string|null $collate The collation for the column.
     * @param int|null $srid The SRID for spatial columns.
     * @param string|null $encoding The character set encoding for the column.
     * @param string|null $baseType The base type for the column.
     * @return void
     */
    public function __construct(
        protected string $name = '',
        protected string $type = '',
        protected ?bool $null = null,
        protected mixed $default = null,
        protected ?int $length = null,
        protected bool $identity = false,
        protected ?string $generated = PostgresAdapter::GENERATED_BY_DEFAULT,
        protected ?int $precision = null,
        protected ?int $increment = null,
        protected ?string $after = null,
        protected ?string $onUpdate = null,
        protected ?string $comment = null,
        protected ?bool $unsigned = null,
        protected ?string $collate = null,
        protected ?int $srid = null,
        protected ?string $encoding = null,
        protected ?string $baseType = null,
    ) {
        $this->null = $null ?? (bool)Configure::read('Migrations.column_null_default');
    }

    /**
     * Sets the column name.
     *
     * @param string $name Name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the column name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Sets the column limit.
     *
     * @param int|null $limit Limit
     * @return $this
     * @deprecated 5.0 Use setLength() instead.
     */
    public function setLimit(?int $limit)
    {
        $this->length = $limit;

        return $this;
    }

    /**
     * Gets the column limit.
     *
     * @return int|null
     * @deprecated 5.0 Use getLength() instead.
     */
    public function getLimit(): ?int
    {
        return $this->length;
    }

    /**
     * Sets whether the column allows nulls.
     *
     * @param bool $null Null
     * @return $this
     */
    public function setNull(bool $null)
    {
        $this->null = $null;

        return $this;
    }

    /**
     * Gets whether the column allows nulls.
     *
     * @return bool
     */
    public function getNull(): bool
    {
        return $this->null;
    }

    /**
     * Does the column allow nulls?
     *
     * @return bool
     */
    public function isNull(): bool
    {
        return $this->getNull();
    }

    /**
     * Sets the default column value.
     *
     * @param mixed $default Default
     * @return $this
     */
    public function setDefault(mixed $default)
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Gets the default column value.
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Sets generated option for identity columns. Ignored otherwise.
     *
     * @param string|null $generated Generated option
     * @return $this
     */
    public function setGenerated(?string $generated)
    {
        $this->generated = $generated;

        return $this;
    }

    /**
     * Gets generated option for identity columns. Null otherwise
     *
     * @return string|null
     */
    public function getGenerated(): ?string
    {
        return $this->generated;
    }

    /**
     * Sets whether the column is an identity column.
     *
     * @param bool $identity Identity
     * @return $this
     */
    public function setIdentity(bool $identity)
    {
        $this->identity = $identity;

        return $this;
    }

    /**
     * Gets whether the column is an identity column.
     *
     * @return bool
     */
    public function getIdentity(): bool
    {
        return $this->identity;
    }

    /**
     * Is the column an identity column?
     *
     * @return bool
     */
    public function isIdentity(): bool
    {
        return $this->getIdentity();
    }

    /**
     * Sets the name of the column to add this column after.
     *
     * @param string $after After
     * @return $this
     */
    public function setAfter(string $after)
    {
        $this->after = $after;

        return $this;
    }

    /**
     * Returns the name of the column to add this column after.
     *
     * @return string|null
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }

    /**
     * Sets the 'ON UPDATE' mysql column function.
     *
     * @param string $update On Update function
     * @return $this
     */
    public function setUpdate(string $update)
    {
        $this->update = $update;

        return $this;
    }

    /**
     * Returns the value of the ON UPDATE column function.
     *
     * @return string|null
     */
    public function getUpdate(): ?string
    {
        return $this->update;
    }

    /**
     * Sets the number precision for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int|null $precision Number precision
     * @return $this
     */
    public function setPrecision(?int $precision)
    {
        $this->setLimit($precision);

        return $this;
    }

    /**
     * Gets the number precision for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @return int|null
     * @deprecated 5.0 Use getLength() instead.
     */
    public function getPrecision(): ?int
    {
        return $this->length;
    }

    /**
     * Sets the column identity increment.
     *
     * @param int $increment Number increment
     * @return $this
     */
    public function setIncrement(int $increment)
    {
        $this->increment = $increment;

        return $this;
    }

    /**
     * Gets the column identity increment.
     *
     * @return int|null
     */
    public function getIncrement(): ?int
    {
        return $this->increment;
    }

    /**
     * Sets the column identity seed.
     *
     * @param int $seed Number seed
     * @return $this
     */
    public function setSeed(int $seed)
    {
        $this->seed = $seed;

        return $this;
    }

    /**
     * Gets the column identity seed.
     *
     * @return int
     */
    public function getSeed(): ?int
    {
        return $this->seed;
    }

    /**
     * Sets the number scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int|null $scale Number scale
     * @return $this
     */
    public function setScale(?int $scale)
    {
        $this->scale = $scale;

        return $this;
    }

    /**
     * Gets the number scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @return int
     */
    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * Sets the number precision and scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int $precision Number precision
     * @param int $scale Number scale
     * @return $this
     */
    public function setPrecisionAndScale(int $precision, int $scale)
    {
        $this->setLimit($precision);
        $this->scale = $scale;

        return $this;
    }

    /**
     * Sets the column comment.
     *
     * @param string|null $comment Comment
     * @return $this
     */
    public function setComment(?string $comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Gets the column comment.
     *
     * @return string
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Gets whether field should be unsigned.
     *
     * Checks configuration options to determine unsigned behavior:
     * - If explicitly set via setUnsigned/setSigned, uses that value
     * - If identity column and Migrations.unsigned_primary_keys is true, returns true
     * - If integer type and Migrations.unsigned_ints is true, returns true
     * - Otherwise defaults to false (signed)
     *
     * @return bool
     */
    public function getUnsigned(): bool
    {
        // If explicitly set, use that value
        if ($this->unsigned !== null) {
            return $this->unsigned;
        }

        $integerTypes = [
            self::INTEGER,
            self::BIGINTEGER,
            self::SMALLINTEGER,
            self::TINYINTEGER,
        ];

        // Only apply configuration to integer types
        if (!in_array($this->type, $integerTypes, true)) {
            return false;
        }

        // Check if this is a primary key/identity column
        if ($this->identity && Configure::read('Migrations.unsigned_primary_keys')) {
            return true;
        }

        // Check general integer configuration
        if (Configure::read('Migrations.unsigned_ints')) {
            return true;
        }

        // Default to signed for backward compatibility
        return false;
    }

    /**
     * Sets whether field should be unsigned.
     *
     * @param bool $unsigned Unsigned
     * @return $this
     */
    public function setUnsigned(bool $unsigned)
    {
        $this->unsigned = $unsigned;

        return $this;
    }

    /**
     * Sets whether field should be signed.
     *
     * @param bool $signed Signed
     * @return $this
     * @deprecated 5.0 Use setUnsigned() instead.
     */
    public function setSigned(bool $signed)
    {
        $this->unsigned = !$signed;

        return $this;
    }

    /**
     * Gets whether field should be signed.
     *
     * @return bool
     * @deprecated 5.0 Use getUnsigned() instead.
     */
    public function getSigned(): bool
    {
        return !$this->isUnsigned();
    }

    /**
     * Sets whether the field should have a timezone identifier.
     * Used for date/time columns only!
     *
     * @param bool $timezone Timezone
     * @return $this
     */
    public function setTimezone(bool $timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Gets whether field has a timezone identifier.
     *
     * @return bool
     */
    public function getTimezone(): bool
    {
        return $this->timezone;
    }

    /**
     * Should the column have a timezone?
     *
     * @return bool
     */
    public function isTimezone(): bool
    {
        return $this->getTimezone();
    }

    /**
     * Sets field properties.
     *
     * @param array $properties Properties
     * @return $this
     */
    public function setProperties(array $properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Gets field properties
     *
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Sets field values.
     *
     * @param string[]|string $values Value(s)
     * @return $this
     */
    public function setValues(array|string $values)
    {
        if (!is_array($values)) {
            $values = preg_split('/,\s*/', $values) ?: [];
        }
        $this->values = $values;

        return $this;
    }

    /**
     * Gets field values
     *
     * @return array|null
     */
    public function getValues(): ?array
    {
        return $this->values;
    }

    /**
     * Sets the column collation.
     *
     * @param string $collation Collation
     * @return $this
     * @deprecated 5.0 Use setCollate() instead.
     */
    public function setCollation(string $collation)
    {
        $this->collate = $collation;

        return $this;
    }

    /**
     * Gets the column collation.
     *
     * @return string|null
     * @deprecated 5.0 Use getCollate() instead.
     */
    public function getCollation(): ?string
    {
        return $this->collate;
    }

    /**
     * Sets the column character set.
     *
     * @param string $encoding Encoding
     * @return $this
     */
    public function setEncoding(string $encoding)
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Gets the column character set.
     *
     * @return string|null
     */
    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * Sets the ALTER TABLE algorithm (MySQL-specific).
     *
     * @param string $algorithm Algorithm
     * @return $this
     */
    public function setAlgorithm(string $algorithm)
    {
        $this->algorithm = $algorithm;

        return $this;
    }

    /**
     * Gets the ALTER TABLE algorithm.
     *
     * @return string|null
     */
    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    /**
     * Sets the ALTER TABLE lock mode (MySQL-specific).
     *
     * @param string $lock Lock mode
     * @return $this
     */
    public function setLock(string $lock)
    {
        $this->lock = $lock;

        return $this;
    }

    /**
     * Gets the ALTER TABLE lock mode.
     *
     * @return string|null
     */
    public function getLock(): ?string
    {
        return $this->lock;
    }

    /**
     * Sets whether field should use fixed-length storage (for binary columns).
     *
     * When true, binary columns will use BINARY(n) instead of VARBINARY(n).
     *
     * @param bool $fixed Fixed
     * @return $this
     */
    public function setFixed(bool $fixed)
    {
        $this->fixed = $fixed;

        return $this;
    }

    /**
     * Gets whether field should use fixed-length storage.
     *
     * @return bool|null
     */
    public function getFixed(): ?bool
    {
        return $this->fixed;
    }

    /**
     * Gets all allowed options. Each option must have a corresponding `setFoo` method.
     *
     * @return array
     */
    protected function getValidOptions(): array
    {
        return [
            'limit',
            'default',
            'null',
            'identity',
            'scale',
            'after',
            'update',
            'comment',
            'signed',
            'unsigned',
            'timezone',
            'properties',
            'values',
            'collation',
            'collate',
            'encoding',
            'srid',
            'seed',
            'increment',
            'generated',
            'algorithm',
            'lock',
            'fixed',
        ];
    }

    /**
     * Gets all aliased options. Each alias must reference a valid option.
     *
     * @return array
     */
    protected function getAliasedOptions(): array
    {
        return [
            'length' => 'limit',
            'precision' => 'limit',
            'autoIncrement' => 'identity',
            'collation' => 'collate',
        ];
    }

    /**
     * Utility method that maps an array of column options to this object's methods.
     *
     * @param array<string, mixed> $options Options
     * @throws \RuntimeException
     * @return $this
     */
    public function setOptions(array $options)
    {
        $validOptions = $this->getValidOptions();
        $aliasOptions = $this->getAliasedOptions();

        if (isset($options['identity']) && $options['identity'] && !isset($options['null'])) {
            $options['null'] = false;
        }

        foreach ($options as $option => $value) {
            if (isset($aliasOptions[$option])) {
                // proxy alias -> option
                $option = $aliasOptions[$option];
            }

            if (!in_array($option, $validOptions, true)) {
                throw new RuntimeException(sprintf('"%s" is not a valid column option.', $option));
            }

            $method = 'set' . ucfirst($option);
            $this->$method($value);
        }

        return $this;
    }

    /**
     * Convert the column into the array shape
     * used by cakephp/database.
     *
     * @return array
     */
    public function toArray(): array
    {
        $default = $this->getDefault();
        if ($default instanceof Literal) {
            $default = new QueryExpression((string)$default);
        }

        $type = $this->getType();
        $length = $this->getLimit();
        $precision = $this->getPrecision();
        $scale = $this->getScale();
        if ($precision !== null) {
            if ($type === self::TIMESTAMP) {
                $type = 'timestampfractional';
            } elseif ($type === self::DATETIME) {
                $type = 'datetimefractional';
            }
        }
        // Decimal types in cakephp/database use
        // (length, precision) while phinx used (precision ?? length, scale)
        if ($type === self::DECIMAL) {
            $length = $precision ?? $length;
            $precision = $scale;
        } else {
            $precision = $scale ?? $precision;
        }

        return [
            'name' => $this->getName(),
            'type' => $type,
            'length' => $length,
            'null' => $this->getNull(),
            'default' => $default,
            'generated' => $this->getGenerated(),
            'unsigned' => $this->getUnsigned(),
            'fixed' => $this->getFixed(),
            'onUpdate' => $this->getUpdate(),
            'collate' => $this->getCollation(),
            'precision' => $precision,
            'srid' => $this->getSrid(),
            'timezone' => $this->getTimezone(),
            'comment' => $this->getComment(),
            'autoIncrement' => $this->getIdentity(),
            'values' => $this->getValues(),
        ];
    }
}
