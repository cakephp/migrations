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
namespace TestApp\Model\Table;

use Cake\ORM\Table;

/**
 * Numbers Model
 */
class NumbersTable extends Table
{
    public function initialize(array $config): void
    {
        $db = env('DB');
        $schema = 'cakephp_test.';
        if ($db === 'pgsql') {
            $schema = '';
        }

        $this->setTable($schema . 'numbers');
    }
}
