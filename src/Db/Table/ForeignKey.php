<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use Cake\Database\Schema\ForeignKey as DatabaseForeignKey;
use InvalidArgumentException;
use RuntimeException;

/**
 * Foreign key value object
 *
 * Used to define foreign keys that are added to tables as part of migrations.
 *
 * @see \Migrations\BaseMigration::foreignKey()
 * @see \Migrations\Db\Table::addForeignKey()
 */
class ForeignKey extends DatabaseForeignKey
{
    public const CASCADE = 'CASCADE';
    public const RESTRICT = 'RESTRICT';
    public const SET_NULL = 'SET NULL';
    public const NO_ACTION = 'NO ACTION';
    public const DEFERRED = 'DEFERRABLE INITIALLY DEFERRED';
    public const IMMEDIATE = 'DEFERRABLE INITIALLY IMMEDIATE';
    public const NOT_DEFERRED = 'NOT DEFERRABLE';

    /**
     * An allow list of valid actions
     *
     * Both the constant values from CakePHP and backwards compatibility with migrations
     * are supported.
     *
     * @var array<string>
     */
    protected array $validActions = [
        DatabaseForeignKey::CASCADE,
        DatabaseForeignKey::RESTRICT,
        DatabaseForeignKey::SET_NULL,
        DatabaseForeignKey::NO_ACTION,
        DatabaseForeignKey::SET_DEFAULT,
        self::CASCADE,
        self::RESTRICT,
        self::SET_NULL,
        self::NO_ACTION,
        self::SET_DEFAULT,
        'NO_ACTION',
        'SET_DEFAULT',
        'SET_NULL',
    ];

    /**
     * @var array<string>
     */
    protected static array $validOptions = ['delete', 'update', 'constraint', 'name', 'deferrable'];

    /**
     * Constructor
     *
     * @param string $name The name of the index.
     * @param array<string> $columns The columns to index.
     * @param ?string $referencedTable The columns to index.
     * @param array<string> $referencedColumns The columns in $referencedTable that this key references.
     * @param ?string $delete The action to take when the referenced row is deleted.
     * @param ?string $update The action to take when the referenced row is updated.
     */
    public function __construct(
        protected string $name = '',
        protected array $columns = [],
        protected ?string $referencedTable = null,
        protected array $referencedColumns = [],
        ?string $delete = null,
        ?string $update = null,
        ?string $deferrable = null,
    ) {
        $this->type = self::FOREIGN;
        $this->delete = $this->normalizeAction($delete ?? self::NO_ACTION);
        $this->update = $this->normalizeAction($update ?? self::NO_ACTION);
        if ($deferrable) {
            $this->deferrable = $this->normalizeDeferrable($deferrable);
        }
    }

    /**
     * Utility method that maps an array of index options to this object's methods.
     *
     * @param array<string, mixed> $options Options
     * @throws \RuntimeException
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $option => $value) {
            if (!in_array($option, static::$validOptions, true)) {
                throw new RuntimeException(sprintf('"%s" is not a valid foreign key option.', $option));
            }

            // handle $options['delete'] and $options['update']
            if ($option === 'delete') {
                $this->delete = $this->normalizeAction($value);
            } elseif ($option === 'update') {
                $this->update = $this->normalizeAction($value);
            } elseif ($option === 'deferrable') {
                $this->setDeferrableMode($value);
            } else {
                $method = 'set' . ucfirst($option);
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * Convert from migrations sql snippet to cakephp/database constant names.
     *
     * @param string $action The action to normalize
     * @return string
     */
    protected function normalizeAction(string $action): string
    {
        $action = str_replace(' ', '_', strtoupper(trim($action)));
        $result = parent::normalizeAction($action);

        return match ($result) {
            self::CASCADE => DatabaseForeignKey::CASCADE,
            self::RESTRICT => DatabaseForeignKey::RESTRICT,
            self::SET_NULL => DatabaseForeignKey::SET_NULL,
            self::NO_ACTION => DatabaseForeignKey::NO_ACTION,
            self::SET_DEFAULT => DatabaseForeignKey::SET_DEFAULT,
            'NO_ACTION' => DatabaseForeignKey::NO_ACTION,
            'SET_NULL' => DatabaseForeignKey::SET_NULL,
            'SET_DEFAULT' => DatabaseForeignKey::SET_DEFAULT,
            default => throw new InvalidArgumentException(sprintf('Invalid foreign key action: %s', $action)),
        };
    }

    /**
     * Map between cakephp/database constant names and
     * migrations sql snippets.
     *
     * These constants are different for backwards compatibility reasons.
     * Longer term, there should probably be a public API in cakephp/database
     * for converting between constants and sql.
     *
     * @param string $action The action to map
     * @return string
     */
    protected function mapAction(string $action): string
    {
        return match ($action) {
            DatabaseForeignKey::CASCADE => self::CASCADE,
            DatabaseForeignKey::RESTRICT => self::RESTRICT,
            DatabaseForeignKey::SET_NULL => self::SET_NULL,
            DatabaseForeignKey::NO_ACTION => self::NO_ACTION,
            default => $action,
        };
    }

    /**
     * Sets deferrable mode for the foreign key.
     *
     * @param string $deferrableMode Constraint
     * @return $this
     */
    public function setDeferrableMode(string $deferrableMode)
    {
        $this->deferrable = $this->normalizeDeferrable($deferrableMode);

        return $this;
    }

    /**
     * Gets deferrable mode for the foreign key.
     */
    public function getDeferrableMode(): ?string
    {
        return $this->mapDeferrable($this->deferrable);
    }

    /**
     * Convert from migrations sql snippet to cakephp/database constant names.
     *
     * @param string $action The action to normalize
     * @return string
     */
    protected function normalizeDeferrable(string $action): string
    {
        $result = parent::normalizeDeferrable($action);

        return match ($result) {
            self::DEFERRED => DatabaseForeignKey::DEFERRED,
            self::IMMEDIATE => DatabaseForeignKey::IMMEDIATE,
            self::NOT_DEFERRED => DatabaseForeignKey::NOT_DEFERRED,
            'NOT_DEFERRED' => DatabaseForeignKey::NOT_DEFERRED,
            default => throw new InvalidArgumentException(sprintf('Invalid foreign key deferrable: %s', $action)),
        };
    }

    /**
     * Map between cakephp/database constant names and
     * migrations sql snippets.
     *
     * These constants are different for backwards compatibility reasons.
     * Longer term, there should probably be a public API in cakephp/database
     * for converting between constants and sql.
     *
     * @param string $action The action to map
     * @return ?string
     */
    protected function mapDeferrable(?string $action): ?string
    {
        return match ($action) {
            DatabaseForeignKey::DEFERRED => self::DEFERRED,
            DatabaseForeignKey::IMMEDIATE => self::IMMEDIATE,
            DatabaseForeignKey::NOT_DEFERRED => self::NOT_DEFERRED,
            null => null,
            default => $action,
        };
    }

    /**
     * Sets ON DELETE action for the foreign key.
     *
     * @param string $onDelete On Delete action
     * @return $this
     * @deprecated 5.0 Use setDelete() instead.
     */
    public function setOnDelete(string $onDelete)
    {
        $this->delete = $this->normalizeAction($onDelete);

        return $this;
    }

    /**
     * Gets ON DELETE action for the foreign key.
     *
     * @return string|null
     * @deprecated 5.0 Use getDelete() instead.
     */
    public function getOnDelete(): ?string
    {
        return $this->mapAction($this->getDelete());
    }

    /**
     * Sets ON UPDATE action for the foreign key.
     *
     * @param string $onUpdate On update action
     * @return $this
     * @deprecated 5.0 Use setUpdate() instead.
     */
    public function setOnUpdate(string $onUpdate)
    {
        $this->update = $this->normalizeAction($onUpdate);

        return $this;
    }

    /**
     * Gets ON UPDATE action for the foreign key.
     *
     * @return string|null
     * @deprecated 5.0 Use getUpdate() instead.
     */
    public function getOnUpdate(): ?string
    {
        return $this->mapAction($this->getUpdate());
    }
}
