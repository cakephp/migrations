<?php
namespace Migrations\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EventsFixture
 */
class EventsFixture extends TestFixture
{
    /**
     * Records
     */
    public array $records = [
        [
            'title' => 'Lorem ipsum dolor sit amet',
            'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.',
            'published' => 'Y',
        ],
        [
            'title' => 'Second event',
            'description' => 'Second event description.',
            'published' => 'Y',
        ],
        [
            'title' => 'Lorem ipsum dolor sit amet',
            'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.
Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget \'sollicitudin\' venenatis cum
nullam, vivamus ut a sed, mollitia lectus.
Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus
duis vestibulum nunc mattis convallis.',
            'published' => 'Y',
        ],
    ];
}
