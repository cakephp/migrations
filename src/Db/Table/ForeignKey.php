<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use InvalidArgumentException;
use RuntimeException;
use function Cake\Core\deprecationWarning;

/**
 * Foreign key value object
 *
 * Used to define foreign keys that are added to tables as part of migrations.
 *
 * @see \Migrations\BaseMigration::foreignKey()
 * @see \Migrations\Db\Table::addForeignKey()
 */
class ForeignKey
{
    public const CASCADE = 'CASCADE';
    public const RESTRICT = 'RESTRICT';
    public const SET_NULL = 'SET NULL';
    public const NO_ACTION = 'NO ACTION';
    public const DEFERRED = 'DEFERRABLE INITIALLY DEFERRED';
    public const IMMEDIATE = 'DEFERRABLE INITIALLY IMMEDIATE';
    public const NOT_DEFERRED = 'NOT DEFERRABLE';

    /**
     * @var array<string>
     */
    protected static array $validOptions = ['delete', 'update', 'constraint', 'name', 'deferrable'];

    /**
     * @var string[]
     */
    protected array $columns = [];

    /**
     * @var \Migrations\Db\Table\Table
     */
    protected Table $referencedTable;

    /**
     * @var string[]
     */
    protected array $referencedColumns = [];

    /**
     * @var string|null
     */
    protected ?string $onDelete = null;

    /**
     * @var string|null
     */
    protected ?string $onUpdate = null;

    /**
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * @var string|null
     */
    protected ?string $deferrableMode = null;

    /**
     * Sets the foreign key columns.
     *
     * @param string[]|string $columns Columns
     * @return $this
     */
    public function setColumns(array|string $columns)
    {
        $this->columns = is_string($columns) ? [$columns] : $columns;

        return $this;
    }

    /**
     * Gets the foreign key columns.
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Sets the foreign key referenced table.
     *
     * @param \Migrations\Db\Table\Table|string $table The table this KEY is pointing to
     * @return $this
     */
    public function setReferencedTable(Table|string $table)
    {
        if (is_string($table)) {
            $table = new Table($table);
        }
        $this->referencedTable = $table;

        return $this;
    }

    /**
     * Gets the foreign key referenced table.
     *
     * @return \Migrations\Db\Table\Table
     */
    public function getReferencedTable(): Table
    {
        return $this->referencedTable;
    }

    /**
     * Sets the foreign key referenced columns.
     *
     * @param string|string[] $referencedColumns Referenced columns
     * @return $this
     */
    public function setReferencedColumns(array|string $referencedColumns)
    {
        $referencedColumns = is_string($referencedColumns) ? [$referencedColumns] : $referencedColumns;
        $this->referencedColumns = $referencedColumns;

        return $this;
    }

    /**
     * Gets the foreign key referenced columns.
     *
     * @return string[]
     */
    public function getReferencedColumns(): array
    {
        return $this->referencedColumns;
    }

    /**
     * Sets ON DELETE action for the foreign key.
     *
     * @param string $onDelete On Delete
     * @return $this
     */
    public function setOnDelete(string $onDelete)
    {
        $this->onDelete = $this->normalizeAction($onDelete);

        return $this;
    }

    /**
     * Gets ON DELETE action for the foreign key.
     *
     * @return string|null
     */
    public function getOnDelete(): ?string
    {
        return $this->onDelete;
    }

    /**
     * Gets ON UPDATE action for the foreign key.
     *
     * @return string|null
     */
    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }

    /**
     * Sets ON UPDATE action for the foreign key.
     *
     * @param string $onUpdate On Update
     * @return $this
     */
    public function setOnUpdate(string $onUpdate)
    {
        $this->onUpdate = $this->normalizeAction($onUpdate);

        return $this;
    }

    /**
     * Set the constraint name for the foreign key.
     *
     * @param string $name Constraint name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the constraint name if set.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Sets constraint for the foreign key.
     *
     * @param string $constraint Constraint
     * @return $this
     */
    public function setConstraint(string $constraint)
    {
        deprecationWarning('4.6.0', 'setConstraint() is deprecated. Use setName() instead.');
        $this->name = $constraint;

        return $this;
    }

    /**
     * Gets constraint name for the foreign key.
     *
     * @return string|null
     */
    public function getConstraint(): ?string
    {
        deprecationWarning('4.6.0', 'getConstraint() is deprecated. Use getName() instead.');

        return $this->name;
    }

    /**
     * Sets deferrable mode for the foreign key.
     *
     * @param string $deferrableMode Constraint
     * @return $this
     */
    public function setDeferrableMode(string $deferrableMode)
    {
        $this->deferrableMode = $this->normalizeDeferrable($deferrableMode);

        return $this;
    }

    /**
     * Gets deferrable mode for the foreign key.
     */
    public function getDeferrableMode(): ?string
    {
        return $this->deferrableMode;
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

            // handle $options['delete'] as $options['update']
            if ($option === 'delete') {
                $this->setOnDelete($value);
            } elseif ($option === 'update') {
                $this->setOnUpdate($value);
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
     * From passed value checks if it's correct and fixes if needed
     *
     * @param string $action Action
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function normalizeAction(string $action): string
    {
        $constantName = 'static::' . str_replace(' ', '_', strtoupper(trim($action)));
        if (!defined($constantName)) {
            throw new InvalidArgumentException('Unknown action passed: ' . $action);
        }

        return constant($constantName);
    }

    /**
     * From passed value checks if it's correct and fixes if needed
     *
     * @param string $deferrable Deferrable
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function normalizeDeferrable(string $deferrable): string
    {
        $mapping = [
            'DEFERRED' => ForeignKey::DEFERRED,
            'IMMEDIATE' => ForeignKey::IMMEDIATE,
            'NOT DEFERRED' => ForeignKey::NOT_DEFERRED,
            ForeignKey::DEFERRED => ForeignKey::DEFERRED,
            ForeignKey::IMMEDIATE => ForeignKey::IMMEDIATE,
            ForeignKey::NOT_DEFERRED => ForeignKey::NOT_DEFERRED,
        ];
        $normalized = strtoupper(str_replace('_', ' ', $deferrable));
        if (array_key_exists($normalized, $mapping)) {
            return $mapping[$normalized];
        }

        throw new InvalidArgumentException('Unknown deferrable passed: ' . $deferrable);
    }
}
