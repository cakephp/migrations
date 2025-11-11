<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

/**
 * Trigger value object
 *
 * Used to define database triggers that are added to tables as part of migrations.
 *
 * @see \Migrations\BaseMigration::trigger()
 * @see \Migrations\Db\Table::createTrigger()
 */
class Trigger
{
    /**
     * @var string
     */
    public const BEFORE = 'BEFORE';

    /**
     * @var string
     */
    public const AFTER = 'AFTER';

    /**
     * @var string
     */
    public const INSTEAD_OF = 'INSTEAD OF';

    /**
     * @var string
     */
    public const INSERT = 'INSERT';

    /**
     * @var string
     */
    public const UPDATE = 'UPDATE';

    /**
     * @var string
     */
    public const DELETE = 'DELETE';

    /**
     * Constructor
     *
     * @param string $name The name of the trigger.
     * @param string $timing When the trigger fires (BEFORE, AFTER, or INSTEAD OF).
     * @param string|array<string> $event The event(s) that fire the trigger (INSERT, UPDATE, DELETE).
     * @param string $definition The trigger body/definition.
     * @param bool $forEach Whether to fire for each row (true) or statement (false).
     */
    public function __construct(
        protected string $name = '',
        protected string $timing = self::BEFORE,
        protected string|array $event = self::INSERT,
        protected string $definition = '',
        protected bool $forEach = true,
    ) {
    }

    /**
     * Sets the trigger name.
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
     * Gets the trigger name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the trigger timing (BEFORE, AFTER, or INSTEAD OF).
     *
     * @param string $timing Timing
     * @return $this
     */
    public function setTiming(string $timing)
    {
        $this->timing = $timing;

        return $this;
    }

    /**
     * Gets the trigger timing.
     *
     * @return string
     */
    public function getTiming(): string
    {
        return $this->timing;
    }

    /**
     * Sets the trigger event(s).
     *
     * @param string|array<string> $event Event(s)
     * @return $this
     */
    public function setEvent(string|array $event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Gets the trigger event(s).
     *
     * @return string|array<string>
     */
    public function getEvent(): string|array
    {
        return $this->event;
    }

    /**
     * Sets the trigger definition/body.
     *
     * @param string $definition Definition
     * @return $this
     */
    public function setDefinition(string $definition)
    {
        $this->definition = $definition;

        return $this;
    }

    /**
     * Gets the trigger definition.
     *
     * @return string
     */
    public function getDefinition(): string
    {
        return $this->definition;
    }

    /**
     * Sets whether to fire for each row (true) or per statement (false).
     *
     * @param bool $forEach For each row flag
     * @return $this
     */
    public function setForEach(bool $forEach)
    {
        $this->forEach = $forEach;

        return $this;
    }

    /**
     * Gets whether to fire for each row.
     *
     * @return bool
     */
    public function getForEach(): bool
    {
        return $this->forEach;
    }
}
