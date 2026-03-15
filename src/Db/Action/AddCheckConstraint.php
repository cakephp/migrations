<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\TableMetadata;

class AddCheckConstraint extends Action
{
    /**
     * The check constraint to add
     *
     * @var \Migrations\Db\Table\CheckConstraint
     */
    protected CheckConstraint $checkConstraint;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to add the check constraint to
     * @param \Migrations\Db\Table\CheckConstraint $checkConstraint The check constraint to add
     */
    public function __construct(TableMetadata $table, CheckConstraint $checkConstraint)
    {
        parent::__construct($table);
        $this->checkConstraint = $checkConstraint;
    }

    /**
     * Creates a new AddCheckConstraint object after building the check constraint with
     * the passed attributes
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table object to add the check constraint to
     * @param string $expression The check constraint expression (e.g., "age >= 18")
     * @param array<string, mixed> $options Options for the check constraint (e.g., 'name')
     * @return self
     */
    public static function build(
        TableMetadata $table,
        string $expression,
        array $options = [],
    ): self {
        $name = $options['name'] ?? '';

        $checkConstraint = new CheckConstraint($name, $expression);

        return new AddCheckConstraint($table, $checkConstraint);
    }

    /**
     * Returns the check constraint to be added
     *
     * @return \Migrations\Db\Table\CheckConstraint
     */
    public function getCheckConstraint(): CheckConstraint
    {
        return $this->checkConstraint;
    }
}
