<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.3.0
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\Command\HelpCommand;
use Cake\Console\CommandCollection;
use Cake\Console\CommandCollectionAwareInterface;
use Cake\Console\ConsoleIoInterface;
use Cake\Console\Exception\ConsoleException;

/**
 * Command that provides help and an entry point to migrations tools.
 */
class EntryCommand extends Command implements CommandCollectionAwareInterface
{
    /**
     * The command collection to get help on.
     */
    protected CommandCollection $commands;

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'migrations';
    }

    /**
     * @inheritDoc
     */
    public function setCommandCollection(CommandCollection $commands): void
    {
        $this->commands = $commands;
    }

    /**
     * Run the command.
     *
     * Override the run() method for special handling of the `--help` option.
     *
     * @param array $argv Arguments from the CLI environment.
     * @param \Cake\Console\ConsoleIoInterface|null $io The console IO.
     * @return int|null Exit code or null for success.
     */
    public function run(array $argv, ?ConsoleIoInterface $io = null): ?int
    {
        if ($io instanceof ConsoleIoInterface) {
            $this->io = $io;
        }
        $this->initialize();

        $parser = $this->getOptionParser();
        try {
            [$options, $arguments] = $parser->parse($argv);
            $this->args = new Arguments(
                $arguments,
                $options,
                $parser->argumentNames(),
            );
        } catch (ConsoleException $e) {
            $this->io->err('Error: ' . $e->getMessage());

            return static::CODE_ERROR;
        }
        $this->setOutputLevel();

        // This is the variance from Command::run()
        if (!$this->args->getArgumentAt(0) && $this->args->getOption('help')) {
            $this->io->out([
                '<info>Migrations</info>',
                '',
                "Migrations provides commands for managing your application's database schema and initial data.",
                '',
            ]);
            $help = $this->getHelp();
            $this->executeCommand($help, []);

            return static::CODE_SUCCESS;
        }

        return $this->execute();
    }

    /**
     * Execute the command.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        if ($this->args->hasArgumentAt(0)) {
            $name = $this->args->getArgumentAt(0);
            $this->io->err(
                sprintf('<error>Could not find migrations command named `%s`.', $name)
                . ' Run `migrations --help` to get a list of commands.</error>',
            );

            return static::CODE_ERROR;
        }
        $this->io->err('<warning>No command provided. Run `migrations --help` to get a list of commands.</warning>');

        return static::CODE_ERROR;
    }

    /**
     * Gets the generated help command
     *
     * @return \Cake\Console\Command\HelpCommand
     */
    public function getHelp(): HelpCommand
    {
        $help = new HelpCommand();
        $commands = [];
        foreach ($this->commands as $command => $class) {
            if (str_starts_with($command, 'migrations')) {
                $parts = explode(' ', $command);

                // Remove `migrations`
                array_shift($parts);
                if ($parts === []) {
                    continue;
                }
                $commands[$command] = $class;
            }
        }

        $CommandCollection = new CommandCollection($commands);
        $help->setCommandCollection($CommandCollection);

        return $help;
    }
}
