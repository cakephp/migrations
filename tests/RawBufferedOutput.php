<?php
declare(strict_types=1);

namespace Migrations\Test;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * RawBufferedOutput is a specialized BufferedOutput that outputs raw "writeln" calls (ie. it doesn't replace the
 * tags like <info>message</info>.
 */
class RawBufferedOutput extends BufferedOutput
{
    /**
     * @param iterable|string $messages
     * @param int $options
     * @return void
     */
    public function writeln(string|iterable $messages, int $options = OutputInterface::OUTPUT_NORMAL): void
    {
        $this->write($messages, true, $options | self::OUTPUT_RAW);
    }
}
