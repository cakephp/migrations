<?php
declare(strict_types=1);

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
namespace Migrations\Test\TestCase\Command;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\Utility\Inflector;
use Migrations\Test\TestCase\TestCase;

/**
 * BakeMigrationSnapshotCommandTest class
 */
class BakeMigrationSnapshotCommandTest extends TestCase
{
    use StringCompareTrait;

    protected array $fixtures = [
        'plugin.Migrations.Users',
        'plugin.Migrations.SpecialTags',
        'plugin.Migrations.SpecialPk',
        'plugin.Migrations.CompositePk',
        'plugin.Migrations.Products',
        'plugin.Migrations.Categories',
        'plugin.Migrations.Parts',
        'plugin.Migrations.Orders',
        'plugin.Migrations.Articles',
        'plugin.Migrations.Texts',
    ];

    /**
     * @var string
     */
    protected $migrationPath;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;
        $this->migrationPath = ROOT . DS . 'config' . DS . 'Migrations' . DS;

        $this->loadPlugins([
            'Migrations' => ['boostrap' => true],
        ]);
        $this->generatedFiles = [];
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        ConnectionManager::drop('alternative');

        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Test that the BakeMigrationSnapshotCommand::getTableNames properly returns the table list
     * when we want tables from a plugin
     *
     * @return void
     */
    public function testGetTableNames()
    {
        /** @var \Migrations\Test\TestCase\Command\TestClassWithSnapshotTrait|\PHPUnit\Framework\MockObject\MockObject $class */
        $class = $this->getMockBuilder(TestClassWithSnapshotTrait::class)
            ->setMethods(['findTables', 'fetchTableName'])
            ->getMock();

        $class->expects($this->any())
            ->method('findTables')
            ->with('TestBlog')
            ->will($this->returnValue(['ArticlesTable.php', 'TagsTable.php']));

        $class->method('fetchTableName')
            ->will($this->onConsecutiveCalls(['articles_tags', 'articles'], ['articles_tags', 'tags']));

        $results = $class->getTableNames('TestBlog');
        $expected = ['articles_tags', 'articles', 'tags'];
        $this->assertEquals(array_values($expected), array_values($results));
    }

    /**
     * Test baking a snapshot
     *
     * @return void
     */
    public function testNotEmptySnapshot()
    {
        $bakeName = $this->getBakeName('TestNotEmptySnapshot');
        $this->exec("bake migration_snapshot {$bakeName} -c test");

        $generatedMigration = glob($this->migrationPath . '*_TestNotEmptySnapshot*.php');
        $this->generatedFiles = $generatedMigration;
        $this->generatedFiles[] = $this->migrationPath . 'schema-dump-test.lock';
        $generatedMigration = basename($generatedMigration[0]);
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
        $this->assertNotEmpty($this->generatedFiles);
        $this->assertCorrectSnapshot($bakeName, file_get_contents($this->generatedFiles[0]));
    }

    /**
     * Test baking a snapshot
     *
     * @return void
     */
    public function testNotEmptySnapshotNoLock()
    {
        $bakeName = $this->getBakeName('TestNotEmptySnapshotNoLock');
        $this->exec("bake migration_snapshot {$bakeName} -c test --no-lock");

        $generatedMigration = glob($this->migrationPath . '*_TestNotEmptySnapshotNoLock*.php');
        $this->generatedFiles = $generatedMigration;
        $this->generatedFiles[] = $this->migrationPath . 'schema-dump-test.lock';
        $generatedMigration = basename($generatedMigration[0]);
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputNotContains('Creating a dump of the new database state...');
        $this->assertNotEmpty($this->generatedFiles);
    }

    /**
     * Test baking a snapshot with the phinx auto-id feature disabled
     *
     * @return void
     */
    public function testAutoIdDisabledSnapshot()
    {
        $bakeName = $this->getBakeName('TestAutoIdDisabledSnapshot');
        $this->exec("bake migration_snapshot {$bakeName} -c test --disable-autoid");

        $generatedMigration = glob($this->migrationPath . '*_TestAutoIdDisabledSnapshot*.php');
        $this->generatedFiles = $generatedMigration;
        $this->generatedFiles[] = $this->migrationPath . 'schema-dump-test.lock';
        $generatedMigration = basename($generatedMigration[0]);
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
        $this->assertNotEmpty($this->generatedFiles);
        $this->assertCorrectSnapshot($bakeName, file_get_contents($this->generatedFiles[0]));
    }

    /**
     * Test baking a snapshot for a plugin
     *
     * @return void
     */
    public function testPluginBlog()
    {
        $bakeName = $this->getBakeName('TestPluginBlog');
        $this->exec("bake migration_snapshot {$bakeName} -c test -p TestBlog");

        $path = ROOT . DS . 'Plugin' . DS . 'TestBlog' . DS . 'config' . DS . 'Migrations' . DS;

        $generatedMigration = glob($path . '*_TestPluginBlog*.php');
        $this->generatedFiles = $generatedMigration;
        $this->generatedFiles[] = $path . 'schema-dump-test.lock';
        $generatedMigration = basename($generatedMigration[0]);
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
        $this->assertNotEmpty($this->generatedFiles);
        $this->assertCorrectSnapshot($bakeName, file_get_contents($this->generatedFiles[0]));
    }

    /**
     * Test that using MigrationSnapshotTask::fetchTableName in a Table object class
     * where the table name is composed with the database name (e.g. mydb.mytable)
     * will return :
     * - only the table name if the current connection `database` parameter is the first part
     * of the table name
     * - the full string (e.g. mydb.mytable) if the current connection `database` parameter
     * is not the first part of the table name
     *
     * @return void
     */
    public function testFetchTableNames()
    {
        $class = new TestClassWithSnapshotTrait();
        $class->connection = 'alternative';
        $expected = ['alternative.special_tags'];
        $this->assertEquals($expected, $class->fetchTableName('SpecialTagsTable.php', 'TestBlog'));

        ConnectionManager::setConfig('alternative', [
            'database' => 'alternative',
        ]);
        $class->connection = 'alternative';
        $expected = ['special_tags'];
        $this->assertEquals($expected, $class->fetchTableName('SpecialTagsTable.php', 'TestBlog'));

        ConnectionManager::drop('alternative');
        ConnectionManager::setConfig('alternative', [
            'schema' => 'alternative',
        ]);
        $class->connection = 'alternative';
        $expected = ['special_tags'];
        $this->assertEquals($expected, $class->fetchTableName('SpecialTagsTable.php', 'TestBlog'));
    }

    /**
     * Get the baked filename based on the current db environment
     *
     * @param string $name Name of the baked file, unaware of the DB environment
     * @return string Baked filename
     */
    public function getBakeName($name)
    {
        $dbenv = getenv('DB');
        if ($dbenv !== 'mysql') {
            $name .= ucfirst($dbenv);
        } else {
            $dbv = getenv('DBV');
            if (!empty($dbv)) {
                $name .= $dbv;
            }
        }

        return $name;
    }

    /**
     * Assert that the $result matches the content of the baked file
     *
     * @param string $bakeName Name of the file to compare to the test
     * @param string $result Results generated by the test to be compared
     * @return void
     */
    public function assertCorrectSnapshot($bakeName, $result)
    {
        $dbenv = getenv('DB');
        $bakeName = Inflector::underscore($bakeName);
        if (file_exists($this->_compareBasePath . $dbenv . DS . $bakeName . '.php')) {
            $this->assertSameAsFile($dbenv . DS . $bakeName . '.php', $result);
        } else {
            $this->assertSameAsFile($bakeName . '.php', $result);
        }
    }
}
