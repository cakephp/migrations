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

namespace Migrations\Test;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Complete override of the Symfony class needed to have a charged instance of the output object used in the tests.
 * The output object used should be the one of the command.
 */
class CommandTester
{
    /**
     * @var \Symfony\Component\Console\Command\Command
     */
    private $command;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @var array<string>
     */
    private $inputs = [];

    /**
     * @var int
     */
    private $statusCode;

    /**
     * Constructor.
     *
     * @param \Symfony\Component\Console\Command\Command $command A Command instance to test
     */
    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Executes the command.
     *
     * Available execution options:
     *
     *  * interactive: Sets the input interactive flag
     *  * decorated:   Sets the output decorated flag
     *  * verbosity:   Sets the output verbosity flag
     *
     * @param array<string, mixed> $input   An array of command arguments and options
     * @param array<string, mixed> $options An array of execution options
     * @return int The command exit code
     */
    public function execute(array $input, array $options = [])
    {
        // set the command name automatically if the application requires
        // this argument and no command name was passed
        $application = $this->command->getApplication();
        if (
            !isset($input['command'])
            && ($application !== null)
            && $application->getDefinition()->hasArgument('command')
        ) {
            $input += ['command' => $this->command->getName()];
        }

        $this->input = new ArrayInput($input);
        if ($this->inputs) {
            $this->input->setStream(self::createStream($this->inputs));
        }

        if (isset($options['interactive'])) {
            $this->input->setInteractive($options['interactive']);
        }

        // This is where the magic does its magic : we use the output object of the command.
        $this->output = $this->command->getManager()->getOutput();
        $this->output->setDecorated($options['decorated'] ?? false);
        if (isset($options['verbosity'])) {
            $this->output->setVerbosity($options['verbosity']);
        }

        return $this->statusCode = $this->command->run($this->input, $this->output);
    }

    /**
     * Gets the display returned by the last execution of the command.
     *
     * @param bool $normalize Whether to normalize end of lines to \n or not
     * @return string The display
     */
    public function getDisplay($normalize = false)
    {
        rewind($this->output->getStream());

        $display = stream_get_contents($this->output->getStream());

        if ($normalize) {
            $display = str_replace(PHP_EOL, "\n", $display);
        }

        return $display;
    }

    /**
     * Gets the input instance used by the last execution of the command.
     *
     * @return \Symfony\Component\Console\Input\InputInterface The current input instance
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Gets the output instance used by the last execution of the command.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface The current output instance
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Gets the status code returned by the last execution of the application.
     *
     * @return int The status code
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Sets the user inputs.
     *
     * @param array<string> $inputs An array of strings representing each input
     *   passed to the command input stream.
     * @return $this
     */
    public function setInputs(array $inputs)
    {
        $this->inputs = $inputs;

        return $this;
    }

    /**
     * @param array<string> $inputs
     * @return resource|false
     */
    private static function createStream(array $inputs)
    {
        $stream = fopen('php://memory', 'r+', false);

        fwrite($stream, implode(PHP_EOL, $inputs));
        rewind($stream);

        return $stream;
    }
}
