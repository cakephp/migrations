<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use InvalidArgumentException;

/**
 * Check constraint value object
 *
 * Used to define check constraints that are added to tables as part of migrations.
 */
class CheckConstraint
{
    /**
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * @var string
     */
    protected string $expression;

    /**
     * Constructor
     *
     * @param string|null $name Constraint name (optional, will be auto-generated if null)
     * @param string $expression The check constraint expression (e.g., "age >= 18")
     */
    public function __construct(?string $name = null, string $expression = '')
    {
        if ($name !== null) {
            $this->name = $name;
        }
        if ($expression !== '') {
            $this->expression = $expression;
        }
    }

    /**
     * Set the constraint name.
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
     * Get the constraint name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the check constraint expression.
     *
     * @param string $expression The SQL expression for the check constraint
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setExpression(string $expression)
    {
        if (trim($expression) === '') {
            throw new InvalidArgumentException('Check constraint expression cannot be empty');
        }

        $this->expression = $expression;

        return $this;
    }

    /**
     * Get the check constraint expression.
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }
}
