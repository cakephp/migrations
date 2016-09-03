<?php
namespace Migrations\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EventsFixture
 *
 */
class EventsFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'string', 'null' => true],
        'description' => 'text',
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]]
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'title' => 'Lorem ipsum dolor sit amet',
            'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. 
Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget \'sollicitudin\' venenatis cum 
nullam, vivamus ut a sed, mollitia lectus. 
Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus 
duis vestibulum nunc mattis convallis.',
        ],
        [
            'id' => 2,
            'title' => 'Lorem ipsum dolor sit amet',
            'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.',
        ],
    ];
}
