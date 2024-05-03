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

namespace Migrations\Shim;

use Cake\Console\ConsoleIo;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Traversable;

class OutputAdapter implements OutputInterface
{
    /**
     * Constructor
     *
     * @param \Cake\Console\ConsoleIo $io The io instance to wrap
     */
    public function __construct(private ConsoleIo $io)
    {
    }

    /**
     * @inheritDoc
     */
    public function write(string|iterable $messages, bool $newline = false, $options = 0): void
    {
        if ($messages instanceof Traversable) {
            $messages = iterator_to_array($messages);
        }
        $this->io->out($messages, $newline ? 1 : 0);
    }

    /**
     * @inheritDoc
     */
    public function writeln(string|iterable $messages, $options = 0): void
    {
        if ($messages instanceof Traversable) {
            $messages = iterator_to_array($messages);
        }
        $this->io->out($messages, 1);
    }

    /**
     * Sets the verbosity of the output.
     *
     * @param self::VERBOSITY_* $level
     * @return void
     */
    public function setVerbosity(int $level): void
    {
        // TODO map values
        $this->io->level($level);
    }

    /**
     * Gets the current verbosity of the output.
     *
     * @return self::VERBOSITY_*
     */
    public function getVerbosity(): int
    {
        // TODO map values
        return $this->io->level();
    }

    /**
     * Returns whether verbosity is quiet (-q).
     */
    public function isQuiet(): bool
    {
        return $this->io->level() === ConsoleIo::QUIET;
    }

    /**
     * Returns whether verbosity is verbose (-v).
     */
    public function isVerbose(): bool
    {
        return $this->io->level() === ConsoleIo::VERBOSE;
    }

    /**
     * Returns whether verbosity is very verbose (-vv).
     */
    public function isVeryVerbose(): bool
    {
        return false;
    }

    /**
     * Returns whether verbosity is debug (-vvv).
     */
    public function isDebug(): bool
    {
        return false;
    }

    /**
     * Sets the decorated flag.
     *
     * @return void
     */
    public function setDecorated(bool $decorated): void
    {
        throw new RuntimeException('setDecorated is not implemented');
    }

    /**
     * Gets the decorated flag.
     */
    public function isDecorated(): bool
    {
        throw new RuntimeException('isDecorated is not implemented');
    }

    /**
     * @return void
     */
    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        throw new RuntimeException('setFormatter is not implemented');
    }

    /**
     * Returns current output formatter instance.
     */
    public function getFormatter(): OutputFormatterInterface
    {
        throw new RuntimeException('getFormatter is not implemented');
    }
}
