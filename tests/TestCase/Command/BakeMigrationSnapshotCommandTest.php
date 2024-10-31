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
namespace Migrations\Test\TestCase\Command;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\Utility\Inflector;
use Migrations\Test\TestCase\TestCase;
use function Cake\Core\env;

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

        $this->loadPlugins(['SimpleSnapshot']);
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;
        $this->migrationPath = ROOT . DS . 'config' . DS . 'Migrations' . DS;

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
     * Test baking a snapshot
     *
     * @return void
     */
    public function testNotEmptySnapshot()
    {
        $this->runSnapshotTest('NotEmpty');
    }

    /**
     * Test baking a snapshot
     *
     * @return void
     */
    public function testNotEmptySnapshotNoLock()
    {
        $bakeName = $this->getBakeName('TestNotEmptySnapshot');
        $this->exec("bake migration_snapshot {$bakeName} -c test --no-lock");

        $generatedMigration = glob($this->migrationPath . '*_TestNotEmptySnapshot*.php');
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
        $this->runSnapshotTest('AutoIdDisabled', '--disable-autoid');
    }

    /**
     * Tests that baking a diff with signed primary keys is auto-id compatible
     * when `Migrations.unsigned_primary_keys` is disabled.
     */
    public function testSnapshotWithAutoIdCompatibleSignedPrimaryKeys(): void
    {
        $this->skipIf(env('DB') !== 'mysql');

        Configure::write('Migrations.unsigned_primary_keys', false);
        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'SimpleSnapshot' . DS . 'config' . DS . 'Migrations' . DS;

        $connection = ConnectionManager::get('test');
        assert($connection instanceof Connection);

        $connection->execute('ALTER TABLE events CHANGE COLUMN id id INT AUTO_INCREMENT');

        $this->runSnapshotTest('WithAutoIdCompatibleSignedPrimaryKeys', '-p SimpleSnapshot');

        $connection->execute('ALTER TABLE events CHANGE COLUMN id id INT UNSIGNED AUTO_INCREMENT');
    }

    /**
     * Tests that baking a diff with signed primary keys is not auto-id compatible
     * when using the default settings.
     */
    public function testSnapshotWithAutoIdIncompatibleSignedPrimaryKeys(): void
    {
        $this->skipIf(env('DB') !== 'mysql');

        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'SimpleSnapshot' . DS . 'config' . DS . 'Migrations' . DS;

        $connection = ConnectionManager::get('test');
        assert($connection instanceof Connection);

        $connection->execute('ALTER TABLE events CHANGE COLUMN id id INT AUTO_INCREMENT');

        $this->runSnapshotTest('WithAutoIdIncompatibleSignedPrimaryKeys', '-p SimpleSnapshot');

        $connection->execute('ALTER TABLE events CHANGE COLUMN id id INT UNSIGNED AUTO_INCREMENT');
    }

    /**
     * Tests that baking a diff with unsigned primary keys is not auto-id compatible
     * when `Migrations.unsigned_primary_keys` is disabled.
     */
    public function testSnapshotDiffWithAutoIdIncompatibleUnsignedPrimaryKeys(): void
    {
        $this->skipIf(env('DB') !== 'mysql');

        Configure::write('Migrations.unsigned_primary_keys', false);
        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'SimpleSnapshot' . DS . 'config' . DS . 'Migrations' . DS;

        $this->runSnapshotTest('WithAutoIdIncompatibleUnsignedPrimaryKeys', '-p SimpleSnapshot');
    }

    /**
     * Test baking a snapshot for a plugin
     *
     * @return void
     */
    public function testPluginBlog()
    {
        $this->loadPlugins(['TestBlog']);
        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'TestBlog' . DS . 'config' . DS . 'Migrations' . DS;

        $this->runSnapshotTest('PluginBlog', '-p TestBlog');
    }

    protected function runSnapshotTest(string $scenario, string $arguments = ''): void
    {
        if ($arguments) {
            $arguments = " $arguments";
        }

        $bakeName = $this->getBakeName("TestSnapshot{$scenario}");
        $this->exec("bake migration_snapshot {$bakeName} -c test{$arguments}");

        $generatedMigration = glob($this->migrationPath . "*_TestSnapshot{$scenario}*.php");
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

    /**
     * Tests that non default collation is used in the initial snapshot.
     */
    public function testSnapshotWithNonDefaultCollation(): void
    {
        $this->skipIf(env('DB') !== 'mysql');
        $this->loadPlugins(['SimpleSnapshot']);

        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'SimpleSnapshot' . DS . 'config' . DS . 'Migrations' . DS;

        $connection = ConnectionManager::get('test');
        assert($connection instanceof Connection);

        $connection->execute('ALTER TABLE events MODIFY title VARCHAR(255) CHARACTER SET utf8 COLLATE utf8mb3_hungarian_ci');
        $this->runSnapshotTest('WithNonDefaultCollation', '-p SimpleSnapshot');
        $connection->execute('ALTER TABLE events MODIFY title VARCHAR(255)');
    }
}
