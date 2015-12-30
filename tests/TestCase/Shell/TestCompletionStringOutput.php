<?php
/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Shell;

use Cake\Console\ConsoleOutput;

/**
 * Class TestCompletionStringOutput
 * Used to store the output of test Console object in order to test the values
 */
class TestCompletionStringOutput extends ConsoleOutput
{

    public $output = '';

    // @codingStandardsIgnoreStart
    protected function _write($message)
    {
        // @codingStandardsIgnoreEnd
        $this->output .= $message;
    }
}
