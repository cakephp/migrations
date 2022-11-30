<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * A fixture for a join table containing additional data
 */
class SpecialTagsFixture extends TestFixture
{
    /**
     * records property
     *
     * @var array
     */
    public $records = [
        [
            'article_id' => 1,
            'tag_id' => 3,
            'highlighted' => false,
            'highlighted_time' => null,
            'author_id' => null,
        ],
        [
            'article_id' => 2,
            'tag_id' => 1,
            'highlighted' => true,
            'highlighted_time' => '2014-06-01 10:10:00',
            'author_id' => null,
        ],
    ];
}
