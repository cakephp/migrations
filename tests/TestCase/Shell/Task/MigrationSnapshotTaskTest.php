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
namespace Migrations\Test\TestCase\Shell\Task;

use Bake\Shell\Task\BakeTemplateTask;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Inflector;

/**
 * MigrationSnapshotTaskTest class
 */
class MigrationSnapshotTaskTest extends TestCase
{
    use StringCompareTrait;

    public $fixtures = [
        'plugin.migrations.users',
        'plugin.migrations.special_tags',
        'plugin.migrations.special_pk',
        'plugin.migrations.composite_pk',
        'plugin.migrations.products',
        'plugin.migrations.categories',
        'plugin.migrations.orders',
        'plugin.migrations.articles'
    ];

    public $autoFixtures = false;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;
        $inputOutput = $this->getMock('Cake\Console\ConsoleIo', [], [], '', false);

        $this->Task = $this->getMock(
            'Migrations\Shell\Task\MigrationSnapshotTask',
            ['in', 'err', 'dispatchShell', '_stop'],
            [$inputOutput]
        );
        $this->Task->name = 'Migration';
        $this->Task->connection = 'test';
        $this->Task->BakeTemplate = new BakeTemplateTask($inputOutput);
        $this->Task->BakeTemplate->initialize();
        $this->Task->BakeTemplate->interactive = false;
    }

    public function testNotEmptySnapshot()
    {
        $this->loadFixtures(
            'Users',
            'SpecialTags',
            'SpecialPk',
            'CompositePk',
            'Categories',
            'Products',
            'Articles'
        );

        $this->Task->params['require-table'] = false;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'BogusPlugin';

        $this->Task->expects($this->once())
            ->method('dispatchShell')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('migrations mark_migrated'),
                    $this->stringContains('-c test -p BogusPlugin')
                )
            );

        $bakeName = $this->getBakeName('TestNotEmptySnapshot');
        $result = $this->Task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);
    }

    public function testAutoIdDisabledSnapshot()
    {
        $this->Task->params['require-table'] = false;
        $this->Task->params['disable-autoid'] = true;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'BogusPlugin';

        $bakeName = $this->getBakeName('TestAutoIdDisabledSnapshot');
        $result = $this->Task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);
    }

    public function testCompositeConstraintsSnapshot()
    {
        $this->skipIf(
            version_compare(Configure::version(), '3.0.8', '<'),
            'Cannot run "testCompositeConstraintsSnapshot" because CakePHP Core feature' .
            'is not implemented in this version'
        );

        $this->loadFixtures(
            'Orders'
        );

        $this->Task->params['require-table'] = false;
        $this->Task->params['connection'] = 'test';

        $bakeName = $this->getBakeName('TestCompositeConstraintsSnapshot');
        $result = $this->Task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);
    }

    public function getBakeName($name)
    {
        $dbenv = getenv("DB");
        if ($dbenv !== 'mysql') {
            $name .= ucfirst($dbenv);
        }

        return $name;
    }

    public function assertCorrectSnapshot($bakeName, $result)
    {
        $dbenv = getenv("DB");
        $bakeName = Inflector::underscore($bakeName);
        if (file_exists($this->_compareBasePath . $dbenv . DS . $bakeName . '.php')) {
            $this->assertSameAsFile($dbenv . DS . $bakeName . '.php', $result);
        } else {
            $this->assertSameAsFile($bakeName . '.php', $result);
        }
    }
}
