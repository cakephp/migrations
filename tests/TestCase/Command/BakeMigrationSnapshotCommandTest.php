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
    protected function setUp(): void
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
    protected function tearDown(): void
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
    public function testNotEmptySnapshot(): void
    {
        $this->runSnapshotTest('NotEmpty');
    }

    /**
     * Test baking a snapshot
     *
     * @return void
     */
    public function testNotEmptySnapshotNoLock(): void
    {
        $bakeName = $this->getBakeName('TestNotEmptySnapshot');
        $this->exec(sprintf('bake migration_snapshot %s -c test --no-lock', $bakeName));

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
     * Test baking a snapshot with --generate-only flag
     *
     * @return void
     */
    public function testSnapshotGenerateOnly(): void
    {
        if (file_exists($this->migrationPath . 'schema-dump-test.lock')) {
            unlink($this->migrationPath . 'schema-dump-test.lock');
        }

        $bakeName = $this->getBakeName('TestGenerateOnlySnapshot');
        $this->exec(sprintf('bake migration_snapshot %s -c test --generate-only', $bakeName));

        $generatedMigration = glob($this->migrationPath . '*_TestGenerateOnlySnapshot*.php');
        $this->generatedFiles = $generatedMigration;

        $this->assertNotEmpty($generatedMigration, 'Migration file should be generated');

        $generatedMigration = basename($generatedMigration[0]);
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputNotContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputNotContains('Creating a dump of the new database state...');

        // The lock file should not be created with --generate-only
        $this->assertFalse(file_exists($this->migrationPath . 'schema-dump-test.lock'), 'Lock file should not be created with --generate-only');
    }

    public function testSnapshotPostgresTimestampTzColumn(): void
    {
        $this->skipIf(env('DB') !== 'pgsql');

        /** @var \Cake\Database\Connection  $connection */
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'CREATE TABLE IF NOT EXISTS postgres_timestamp_tz (id SERIAL PRIMARY KEY, created TIMESTAMPTZ NOT NULL)',
        );

        $scenario = 'PostgresTimestampTz';

        $bakeName = $this->getBakeName('TestSnapshot' . $scenario);
        $this->exec(sprintf('bake migration_snapshot %s -c test', $bakeName));

        $connection->execute('DROP TABLE postgres_timestamp_tz');

        $generatedMigration = glob($this->migrationPath . sprintf('*_TestSnapshot%s*.php', $scenario));
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
     * Test baking a snapshot with the auto-id feature disabled
     *
     * @return void
     */
    public function testAutoIdDisabledSnapshot(): void
    {
        $this->runSnapshotTest('AutoIdDisabled', '--disable-autoid');
    }

    /**
     * Test baking a snapshot with the change() method
     *
     * @return void
     */
    public function testSnapshotWithChange(): void
    {
        $this->runSnapshotTest('WithChange', '--change');
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
    public function testPluginBlog(): void
    {
        $this->loadPlugins(['TestBlog']);
        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'TestBlog' . DS . 'config' . DS . 'Migrations' . DS;

        $this->runSnapshotTest('PluginBlog', '-p TestBlog');
    }

    /**
     * Test baking a snapshot for a plugin with custom connection (issue #463).
     * This tests that when using both --plugin and --connection options,
     * the migration includes all tables from the connection, not just those
     * with Table classes in the plugin.
     *
     * @return void
     */
    public function testPluginWithCustomConnection(): void
    {
        $this->loadPlugins(['SimpleSnapshot']);
        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'SimpleSnapshot' . DS . 'config' . DS . 'Migrations' . DS;

        $bakeName = $this->getBakeName('TestSnapshotPluginCustomConnection');
        $this->exec(sprintf('bake migration_snapshot %s -c test -p SimpleSnapshot', $bakeName));

        $generatedMigration = glob($this->migrationPath . '*_TestSnapshotPluginCustomConnection*.php');
        $this->generatedFiles = $generatedMigration;
        $this->generatedFiles[] = $this->migrationPath . 'schema-dump-test.lock';

        $this->assertNotEmpty($generatedMigration, 'Migration file should be generated');

        $content = file_get_contents($generatedMigration[0]);
        $this->assertStringContainsString('function up()', $content);
        $this->assertStringNotContainsString('public function up(): void {}', $content, 'up() method should not be empty');
        $this->assertStringContainsString('->create()', $content, 'Migration should contain table creation statements');
    }

    protected function runSnapshotTest(string $scenario, string $arguments = ''): void
    {
        if ($arguments) {
            $arguments = ' ' . $arguments;
        }

        $bakeName = $this->getBakeName('TestSnapshot' . $scenario);
        $this->exec(sprintf('bake migration_snapshot %s -c test%s', $bakeName, $arguments));

        $generatedMigration = glob($this->migrationPath . sprintf('*_TestSnapshot%s*.php', $scenario));
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
    public function getBakeName(string $name)
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
     * Override to normalize collation names for MySQL version compatibility
     *
     * @param string $path Path to comparison file
     * @param string $result Actual result
     * @return void
     */
    public function assertSameAsFile(string $path, string $result): void
    {
        if (!file_exists($path)) {
            $path = $this->_compareBasePath . $path;
        }

        $this->_updateComparisons ??= (bool)env('UPDATE_TEST_COMPARISON_FILES');

        if ($this->_updateComparisons) {
            file_put_contents($path, $result);
        }

        $expected = file_get_contents($path);

        // Normalize utf8mb3 to utf8 for MySQL 8.0.30+ compatibility
        $expected = str_replace('utf8mb3_', 'utf8_', $expected);

        $result = str_replace('utf8mb3_', 'utf8_', $result);

        // Normalize unified table name to legacy for comparison
        $result = str_replace("'cake_migrations'", "'phinxlog'", $result);

        $this->assertTextEquals($expected, $result, 'Content does not match file ' . $path);
    }

    /**
     * Assert that the $result matches the content of the baked file
     *
     * @param string $bakeName Name of the file to compare to the test
     * @param string $result Results generated by the test to be compared
     * @return void
     */
    public function assertCorrectSnapshot($bakeName, string $result): void
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

    /**
     * Tests that an ON UPDATE clause is kept in the initial snapshot.
     */
    public function testSnapshotWithOnUpdate(): void
    {
        $this->skipIf(env('DB') !== 'mysql');
        $this->loadPlugins(['SimpleSnapshot']);

        $this->migrationPath = ROOT . DS . 'Plugin' . DS . 'SimpleSnapshot' . DS . 'config' . DS . 'Migrations' . DS;

        $connection = ConnectionManager::get('test');
        assert($connection instanceof Connection);

        $connection->execute('ALTER TABLE users MODIFY updated TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');
        try {
            $this->runSnapshotTest('WithOnUpdate', '-p SimpleSnapshot');
        } finally {
            $connection->execute('ALTER TABLE users MODIFY updated TIMESTAMP NULL DEFAULT NULL');
        }
    }
}
