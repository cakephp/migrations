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

use Cake\Cache\Cache;
use Cake\Console\BaseCommand;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Driver\Sqlserver;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\Utility\Inflector;
use Exception;
use Migrations\Migrations;
use Migrations\Test\TestCase\TestCase;
use function Cake\Core\env;

/**
 * BakeMigrationDiffCommandTest class
 */
class BakeMigrationDiffCommandTest extends TestCase
{
    use StringCompareTrait;

    /**
     * @var string[]
     */
    protected $generatedFiles = [];

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->generatedFiles = [];

        // Clean up any TheDiff migration files from all directories before test starts
        $configPath = ROOT . DS . 'config' . DS;
        $directories = glob($configPath . '*', GLOB_ONLYDIR) ?: [];
        foreach ($directories as $dir) {
            // Clean up TheDiff migration files
            $migrationFiles = glob($dir . DS . '*TheDiff*.php') ?: [];
            foreach ($migrationFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            // Clean up Initial migration files
            $initialMigrationFiles = glob($dir . DS . '*Initial*.php') ?: [];
            foreach ($initialMigrationFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        // Clean up test_decimal_types table if it exists
        if (env('DB_URL_COMPARE')) {
            try {
                $connection = ConnectionManager::get('test_comparisons');
                $connection->execute('DROP TABLE IF EXISTS test_decimal_types');
            } catch (Exception $e) {
                // Ignore errors if connection doesn't exist yet
            }
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Clean up any TheDiff migration files from all directories
        $configPath = ROOT . DS . 'config' . DS;
        $directories = glob($configPath . '*', GLOB_ONLYDIR) ?: [];
        foreach ($directories as $dir) {
            $migrationFiles = glob($dir . DS . '*TheDiff*.php') ?: [];
            foreach ($migrationFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            $initialMigrationFiles = glob($dir . DS . '*Initial*.php') ?: [];
            foreach ($initialMigrationFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        if (env('DB_URL_COMPARE')) {
            // Clean up the comparison database each time. Table order is important.
            $connection = ConnectionManager::get('test_comparisons');
            $tables = ['articles', 'categories', 'comments', 'users', 'orphan_table', 'phinxlog', 'tags', 'test_blog_phinxlog', 'test_decimal_types'];
            foreach ($tables as $table) {
                $connection->execute("DROP TABLE IF EXISTS $table");
            }
            Cache::clear('_cake_model_');
        }
    }

    /**
     * Tests that baking a diff while history is not in sync will trigger an error
     *
     * @return void
     */
    public function testHistoryNotInSync()
    {
        $expectedMessage = 'Your migrations history is not in sync with your migrations files. ' .
            'Make sure all your migrations have been migrated before baking a diff.';

        $this->exec('bake migration_diff NotInSync --connection test');
        $this->assertErrorContains($expectedMessage);
        $this->assertExitCode(BaseCommand::CODE_ERROR);
    }

    /**
     * Tests that baking a diff while history is empty and no migration files exists
     * will fall back to baking a snapshot
     *
     * @return void
     */
    public function testEmptyHistoryNoMigrations()
    {
        $this->exec('bake migration_diff EmptyHistoryNoMigrations -c test -p Blog');

        $path = ROOT . DS . 'Plugin' . DS . 'Blog' . DS . 'config' . DS . 'Migrations' . DS;
        $this->generatedFiles = glob($path . '*_EmptyHistoryNoMigrations.php');

        $this->assertFileExists($path . 'schema-dump-test.lock', 'Cannot test contents, file does not exist.');
        $this->generatedFiles[] = $path . 'schema-dump-test.lock';

        $this->assertOutputContains('Your migrations history is empty and you do not have any migrations files.');
        $this->assertOutputNotContains('Something went wrong during the snapshot baking. Please try again.');
        $this->assertExitCode(BaseCommand::CODE_ERROR);
    }

    /**
     * Tests baking a diff in a custom folder source
     *
     * @return void
     */
    public function testBakeMigrationDiffInCustomFolder()
    {
        $customFolderName = 'CustomMigrationsFolder';
        $this->exec('bake migration_diff MigrationDiffForCustomFolder -c test -s ' . $customFolderName);

        $path = ROOT . DS . 'config' . DS . $customFolderName . DS;
        $this->generatedFiles = glob($path . '*_MigrationDiffForCustomFolder.php');

        $this->assertCount(1, $this->generatedFiles);
        $this->assertFileExists($path . 'schema-dump-test.lock', 'Cannot test contents, file does not exist.');
        $this->generatedFiles[] = $path . 'schema-dump-test.lock';

        $fileName = pathinfo($this->generatedFiles[0], PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
    }

    /**
     * Tests baking a diff with --generate-only flag
     *
     * @return void
     */
    public function testBakeMigrationDiffGenerateOnly()
    {
        //$this->skipIf(!env('DB_URL_COMPARE'));

        // First create a snapshot to have a base for diff
        $this->exec('bake migration_snapshot InitialSnapshot -c test');
        $path = ROOT . DS . 'config' . DS . 'Migrations' . DS;
        $initialSnapshot = glob($path . '*_InitialSnapshot.php');
        $this->generatedFiles = array_merge($this->generatedFiles, $initialSnapshot);
        $this->generatedFiles[] = $path . 'schema-dump-test.lock';

        // Now test the diff with --generate-only
        $this->exec('bake migration_diff MigrationDiffGenerateOnly -c test --generate-only');

        $diffFiles = glob($path . '*_MigrationDiffGenerateOnly.php');

        // A migration file should always be generated when using bake migration_diff
        $this->assertNotEmpty($diffFiles, 'A migration file should be generated');
        $this->generatedFiles = array_merge($this->generatedFiles, $diffFiles);

        $fileName = pathinfo($diffFiles[0], PATHINFO_FILENAME);

        // With --generate-only, the migration should NOT be marked as applied
        $this->assertOutputNotContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputNotContains('Creating a dump of the new database state...');

        // Verify that the migration was not marked as applied
        $this->exec('migrations status -c test');
        // The status command outputs the migration ID (timestamp) only
        $migrationId = preg_replace('/_.*$/', '', $fileName);
        $this->assertOutputContains($migrationId);
        $this->assertOutputContains('down');
    }

    /**
     * Tests baking a diff
     *
     * @return void
     */
    public function testBakingDiff()
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        $this->runDiffBakingTest('Default');
    }

    /**
     * Tests baking a simpler diff than above
     * Introduced after finding a bug when baking a simple diff with less operations
     *
     * @return void
     */
    public function testBakingDiffSimple()
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        $this->runDiffBakingTest('Simple');
    }

    /**
     * Tests baking a simpler diff than above
     * Introduced after finding a bug when baking a simple diff with less operations
     *
     * @return void
     */
    public function testBakingDiffAddRemove()
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        $this->runDiffBakingTest('AddRemove');
    }

    /**
     * Tests that baking a diff with signed primary keys is auto-id compatible
     * when `Migrations.unsigned_primary_keys` is disabled.
     */
    public function testBakingDiffWithAutoIdCompatibleSignedPrimaryKeys(): void
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        Configure::write('Migrations.unsigned_primary_keys', false);

        $this->runDiffBakingTest('WithAutoIdCompatibleSignedPrimaryKeys');
    }

    /**
     * Tests that baking a diff with signed primary keys is not auto-id compatible
     * when using the default settings.
     */
    public function testBakingDiffWithAutoIdIncompatibleSignedPrimaryKeys(): void
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        $this->runDiffBakingTest('WithAutoIdIncompatibleSignedPrimaryKeys');
    }

    /**
     * Tests that baking a diff with unsigned primary keys is not auto-id compatible
     * when `Migrations.unsigned_primary_keys` is disabled.
     */
    public function testBakingDiffWithAutoIdIncompatibleUnsignedPrimaryKeys(): void
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        Configure::write('Migrations.unsigned_primary_keys', false);

        $this->runDiffBakingTest('WithAutoIdIncompatibleUnsignedPrimaryKeys');
    }

    /**
     * Tests baking a diff with decimal column changes
     * Regression test for issue #659
     */
    public function testBakingDiffDecimalChange(): void
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        $this->runDiffBakingTest('DecimalChange');
    }

    /**
     * Tests that baking a diff with --plugin option only includes tables with Table classes
     */
    public function testBakingDiffWithPluginOnlyIncludesTablesWithTableClasses(): void
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        // Create some test tables in the comparison database
        $connection = ConnectionManager::get('test_comparisons');

        // For now, only test MySQL as the original test was MySQL-specific
        $driver = $connection->getDriver();
        if (!($driver instanceof Mysql)) {
            $this->markTestSkipped('This test currently only works with MySQL');
        }

        // Create a table that has a Table class in the TestBlog plugin
        $connection->execute('CREATE TABLE IF NOT EXISTS articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255)
        )');

        // Create a table that does NOT have a Table class in the TestBlog plugin
        $connection->execute('CREATE TABLE IF NOT EXISTS orphan_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255)
        )');

        // Don't create phinxlog - let the migration system handle it

        // Create a schema dump for the initial state (empty)
        $pluginPath = Plugin::path('TestBlog');
        $migrationsPath = $pluginPath . 'config' . DS . 'Migrations' . DS;
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0777, true);
        }

        // Create an initial dummy migration to establish migration history
        $initialMigration = $migrationsPath . '20200101000000_Initial.php';
        file_put_contents($initialMigration, '<?php
use Migrations\BaseMigration;

class Initial extends BaseMigration
{
    public function up(): void
    {
    }

    public function down(): void
    {
    }
}
');
        $this->generatedFiles[] = $initialMigration;

        // Run the initial migration to establish history
        $this->exec('migrations migrate -c test_comparisons -p TestBlog');

        // Now create a schema dump after the initial migration
        $dumpPath = $migrationsPath . 'schema-dump-test_comparisons.lock';
        file_put_contents($dumpPath, serialize([]));
        $this->generatedFiles[] = $dumpPath;

        // Run the diff command with --plugin option
        $this->exec('bake migration_diff TestPluginDiff -c test_comparisons -p TestBlog');

        // Find the generated migration file
        $migrationPath = $pluginPath . 'config' . DS . 'Migrations' . DS;
        $files = glob($migrationPath . '*_TestPluginDiff.php');
        $this->assertNotEmpty($files, 'Migration file was not generated');
        $this->generatedFiles[] = $files[0];

        // Read the generated migration content
        $content = file_get_contents($files[0]);

        // Assert that only the articles table is included (which has ArticlesTable.php)
        $this->assertStringContainsString('$this->table(\'articles\')', $content);

        // Assert that orphan_table is NOT included (no Table class)
        $this->assertStringNotContainsString('orphan_table', $content);

        // Cleanup
        $connection->execute('DROP TABLE IF EXISTS articles');
        $connection->execute('DROP TABLE IF EXISTS orphan_table');
    }

    protected function runDiffBakingTest(string $scenario): void
    {
        $this->skipIf(!env('DB_URL_COMPARE'));

        // Detect database type from connection if DB env is not set
        $db = env('DB') ?: $this->getDbType();

        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . lcfirst($scenario) . DS;

        // DecimalChange uses 'initial_' prefix to avoid class name conflicts
        $prefix = $scenario === 'DecimalChange' ? 'initial_' : 'the_diff_';
        $classPrefix = $scenario === 'DecimalChange' ? 'Initial' : 'TheDiff';

        $diffMigrationsPath = $diffConfigFolder . $prefix . Inflector::underscore($scenario) . '_' . $db . '.php';
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . $db . '.lock';

        $destinationConfigDir = ROOT . DS . 'config' . DS . "MigrationsDiff{$scenario}" . DS;
        $destination = $destinationConfigDir . "20160415220805_{$classPrefix}{$scenario}" . ucfirst($db) . '.php';
        $destinationDumpPath = $destinationConfigDir . 'schema-dump-test_comparisons_' . $db . '.lock';
        copy($diffMigrationsPath, $destination);

        $this->generatedFiles = [
            $destination,
            $destinationDumpPath,
        ];

        $migrations = $this->getMigrations("MigrationsDiff$scenario");
        $migrations->migrate();

        unlink($destination);
        copy($diffDumpPath, $destinationDumpPath);

        $connection = ConnectionManager::get('test_comparisons');
        $connection->deleteQuery()
            ->delete('phinxlog')
            ->where(['version' => 20160415220805])
            ->execute();

        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . lcfirst($scenario) . DS;

        $bakeName = $this->getBakeName("TheDiff{$scenario}");
        $targetFolder = "MigrationsDiff{$scenario}";
        $comparison = lcfirst($scenario);
        $this->exec("custom bake migration_diff {$bakeName} -c test_comparisons --test-target-folder {$targetFolder} --comparison {$comparison}");

        $this->generatedFiles[] = ROOT . DS . 'config' . DS . 'Migrations' . DS . 'schema-dump-test_comparisons.lock';

        $generatedMigration = $this->getGeneratedMigrationName($destinationConfigDir, "*TheDiff$scenario*");
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
        $this->assertCorrectSnapshot($bakeName, file_get_contents($destinationConfigDir . $generatedMigration));

        rename($destinationConfigDir . $generatedMigration, $destination);
        $versionParts = explode('_', $generatedMigration);

        $connection->insertQuery()
            ->insert(['version', 'migration_name', 'start_time', 'end_time'])
            ->into('phinxlog')
            ->values([
                'version' => 20160415220805,
                'migration_name' => $versionParts[1],
                'start_time' => '2016-05-22 16:51:46',
                'end_time' => '2016-05-22 16:51:46',
            ])
            ->execute();
        $this->getMigrations("MigrationsDiff{$scenario}")->rollback(['target' => 'all']);
    }

    /**
     * Detect database type from connection
     *
     * @return string Database type (mysql, pgsql, sqlite, sqlserver)
     */
    protected function getDbType(): string
    {
        $connection = ConnectionManager::get('test_comparisons');
        $driver = $connection->getDriver();

        if ($driver instanceof Mysql) {
            return 'mysql';
        } elseif ($driver instanceof Postgres) {
            return 'pgsql';
        } elseif ($driver instanceof Sqlite) {
            return 'sqlite';
        } elseif ($driver instanceof Sqlserver) {
            return 'sqlserver';
        }

        return 'mysql'; // Default fallback
    }

    /**
     * Get the baked filename based on the current db environment
     *
     * @param string $name Name of the baked file, unaware of the DB environment
     * @return string Baked filename
     */
    public function getBakeName($name)
    {
        $db = getenv('DB');
        if (!$db) {
            $db = $this->getDbType();
        }
        $name .= ucfirst($db);

        return $name;
    }

    /**
     * Gets a Migrations object in order to easily create and drop tables during the
     * tests
     *
     * @param string $source Source folder where migrations are located
     * @return Migrations
     */
    protected function getMigrations($source = 'MigrationsDiff')
    {
        $params = [
            'connection' => 'test_comparisons',
            'source' => $source,
        ];
        $migrations = new Migrations($params);

        return $migrations;
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
        if (!$dbenv) {
            $dbenv = $this->getDbType();
        }
        $bakeName = Inflector::underscore($bakeName);
        if (file_exists($this->_compareBasePath . $dbenv . DS . $bakeName . '.php')) {
            $this->assertSameAsFile($dbenv . DS . $bakeName . '.php', $result);
        } else {
            $this->assertSameAsFile($bakeName . '.php', $result);
        }
    }

    /**
     * Get the generated migration version number
     *
     * @param string $configDir The config directory to look in.
     * @param string $needle The filename pattern to find.
     * @return string
     */
    public function getGeneratedMigrationName($configDir, $needle)
    {
        $files = glob($configDir . $needle);
        $this->assertNotEmpty($files, "Could not find any files matching `{$needle}` in `{$configDir}`");

        // Record the generated file so we can cleanup if the test fails.
        $this->generatedFiles[] = $files[0];

        return basename($files[0]);
    }
}
