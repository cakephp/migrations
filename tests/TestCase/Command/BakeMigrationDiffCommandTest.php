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
namespace Migrations\Test\Command;

use Cake\Console\BaseCommand;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\Utility\Inflector;
use Migrations\Migrations;
use Migrations\Test\TestCase\TestCase;

/**
 * MigrationSnapshotTaskTest class
 */
class BakeMigrationDiffCommandTest extends TestCase
{
    use StringCompareTrait;

    public $out;

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
        $this->loadPlugins([
            'Migrations' => ['boostrap' => true],
        ]);
        $this->generatedFiles = [];
        $this->cleanupDatabase();
        $this->useCommandRunner();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->cleanupDatabase();
    }

    protected function cleanupDatabase()
    {
        $connection = ConnectionManager::get('test');
        $connection->execute('DROP TABLE IF EXISTS articles');
        $connection->execute('DROP TABLE IF EXISTS categories');
        $connection->execute('DROP TABLE IF EXISTS blog_phinxlog');

        $connection = ConnectionManager::get('test_comparisons');
        $connection->execute('DROP TABLE IF EXISTS articles');
        $connection->execute('DROP TABLE IF EXISTS tags');
        $connection->execute('DROP TABLE IF EXISTS categories');
        $connection->execute('DROP TABLE IF EXISTS phinxlog');
        $connection->execute('DROP TABLE IF EXISTS articles_phinxlog');
        $connection->execute('DROP TABLE IF EXISTS users');
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

        $path = ROOT  . DS . 'Plugin' . DS . 'Blog' . DS . 'config' . DS . 'Migrations' . DS;
        $this->generatedFiles = glob($path . '*_EmptyHistoryNoMigrations.php');

        $this->assertFileExists($path . 'schema-dump-test.lock', 'Cannot test contents, file does not exist.');
        $this->generatedFiles[] = $path . 'schema-dump-test.lock';

        $this->assertOutputContains('Your migrations history is empty and you do not have any migrations files.');
        $this->assertOutputNotContains('Something went wrong during the snapshot baking. Please try again.');
        $this->assertExitCode(BaseCommand::CODE_ERROR);
    }

    /**
     * Tests baking a diff
     *
     * @return void
     */
    public function testBakingDiff()
    {
        $this->skipIf(env('DB') === 'sqlite');

        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS;
        $diffMigrationsPath = $diffConfigFolder . 'the_diff_' . env('DB') . '.php';
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        $destinationConfigDir = ROOT . DS . 'config' . DS . 'MigrationsDiff' . DS;
        $destination = $destinationConfigDir . '20160415220805_TheDiff' . ucfirst(env('DB')) . '.php';
        $destinationDumpPath = $destinationConfigDir . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        copy($diffMigrationsPath, $destination);

        $this->generatedFiles = [
            $destination,
            $destinationDumpPath,
        ];

        $this->getMigrations()->migrate();

        unlink($destination);
        copy($diffDumpPath, $destinationDumpPath);

        $connection = ConnectionManager::get('test_comparisons');
        $connection->newQuery()
            ->delete('phinxlog')
            ->where(['version' => 20160415220805])
            ->execute();

        // Create a _phinxlog table to make sure it's not included in the dump
        $table = (new TableSchema('articles_phinxlog'))->addColumn('title', [
            'type' => 'string',
            'length' => 255,
        ]);
        foreach ($table->createSql($connection) as $stmt) {
            $connection->query($stmt);
        }

        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS;
        $bakeName = $this->getBakeName('TheDiff');
        $this->exec("custom bake migration_diff {$bakeName} -c test_comparisons");

        $this->generatedFiles[] = ROOT . DS . 'config' . DS . 'Migrations' . DS . 'schema-dump-test_comparisons.lock';

        $generatedMigration = $this->getGeneratedMigrationName($destinationConfigDir, '*TheDiff*');
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
        $this->assertCorrectSnapshot($bakeName, file_get_contents($destinationConfigDir . $generatedMigration));

        rename($destinationConfigDir . $generatedMigration, $destination);
        $versionParts = explode('_', $generatedMigration);

        $connection->newQuery()
            ->insert(['version', 'migration_name', 'start_time', 'end_time'])
            ->into('phinxlog')
            ->values([
                'version' => 20160415220805,
                'migration_name' => $versionParts[1],
                'start_time' => '2016-05-22 16:51:46',
                'end_time' => '2016-05-22 16:51:46',
            ])
            ->execute();
        $this->getMigrations()->rollback(['target' => 'all']);

        foreach ($table->dropSql($connection) as $stmt) {
            $connection->query($stmt);
        }
    }

    /**
     * Tests baking a simpler diff than above
     * Introduced after finding a bug when baking a simple diff with less operations
     *
     * @return void
     */
    public function testBakingDiffSimple()
    {
        $this->skipIf(env('DB') === 'sqlite');

        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . 'simple' . DS;
        $diffMigrationsPath = $diffConfigFolder . 'the_diff_simple_' . env('DB') . '.php';
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        $destinationConfigDir = ROOT . DS . 'config' . DS . 'MigrationsDiffSimple' . DS;
        $destination = $destinationConfigDir . '20160415220805_TheDiffSimple' . ucfirst(env('DB')) . '.php';
        $destinationDumpPath = $destinationConfigDir . 'schema-dump-test_comparisons_' . env('DB') . '.lock';
        copy($diffMigrationsPath, $destination);

        $this->generatedFiles = [
            $destination,
            $destinationDumpPath,
        ];

        $this->getMigrations('MigrationsDiffSimple')->migrate();

        unlink($destination);
        copy($diffDumpPath, $destinationDumpPath);

        $connection = ConnectionManager::get('test_comparisons');
        $connection->newQuery()
            ->delete('phinxlog')
            ->where(['version' => 20160415220805])
            ->execute();

        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . 'simple' . DS;

        $bakeName = $this->getBakeName('TheDiffSimple');
        $this->exec("customSimple bake migration_diff {$bakeName} -c test_comparisons");

        $this->generatedFiles[] = ROOT . DS . 'config' . DS . 'Migrations' . DS . 'schema-dump-test_comparisons.lock';

        $generatedMigration = $this->getGeneratedMigrationName($destinationConfigDir, '*TheDiffSimple*');
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
        $this->assertCorrectSnapshot($bakeName, file_get_contents($destinationConfigDir . $generatedMigration));

        rename($destinationConfigDir . $generatedMigration, $destination);
        $versionParts = explode('_', $generatedMigration);

        $connection->newQuery()
            ->insert(['version', 'migration_name', 'start_time', 'end_time'])
            ->into('phinxlog')
            ->values([
                'version' => 20160415220805,
                'migration_name' => $versionParts[1],
                'start_time' => '2016-05-22 16:51:46',
                'end_time' => '2016-05-22 16:51:46',
            ])
            ->execute();
        $this->getMigrations('MigrationsDiffSimple')->rollback(['target' => 'all']);
    }

    /**
     * Tests baking a simpler diff than above
     * Introduced after finding a bug when baking a simple diff with less operations
     *
     * @return void
     */
    public function testBakingDiffAddRemove()
    {
        $this->skipIf(env('DB') === 'sqlite');

        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . 'addremove' . DS;
        $diffMigrationsPath = $diffConfigFolder . 'the_diff_add_remove_' . env('DB') . '.php';
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        $destinationConfigDir = ROOT . DS . 'config' . DS . 'MigrationsDiffAddRemove' . DS;
        $destination = $destinationConfigDir . '20160415220805_TheDiffAddRemove' . ucfirst(env('DB')) . '.php';
        $destinationDumpPath = $destinationConfigDir . 'schema-dump-test_comparisons_' . env('DB') . '.lock';
        copy($diffMigrationsPath, $destination);

        $this->generatedFiles = [
            $destination,
            $destinationDumpPath,
        ];

        $this->getMigrations('MigrationsDiffAddRemove')->migrate();

        unlink($destination);
        copy($diffDumpPath, $destinationDumpPath);

        $connection = ConnectionManager::get('test_comparisons');
        $connection->newQuery()
            ->delete('phinxlog')
            ->where(['version' => 20160415220805])
            ->execute();

        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . 'addremove' . DS;

        $bakeName = $this->getBakeName('TheDiffAddRemove');

        $this->exec("customRemove bake migration_diff {$bakeName} -c test_comparisons");

        $this->generatedFiles[] = ROOT . DS . 'config' . DS . 'Migrations' . DS . 'schema-dump-test_comparisons.lock';

        $generatedMigration = $this->getGeneratedMigrationName($destinationConfigDir, '*TheDiff*');
        $fileName = pathinfo($generatedMigration, PATHINFO_FILENAME);
        $this->assertOutputContains('Marking the migration ' . $fileName . ' as migrated...');
        $this->assertOutputContains('Creating a dump of the new database state...');
        $this->assertCorrectSnapshot($bakeName, file_get_contents($destinationConfigDir . $generatedMigration));

        rename($destinationConfigDir . $generatedMigration, $destination);
        $versionParts = explode('_', $generatedMigration);

        $connection->newQuery()
            ->insert(['version', 'migration_name', 'start_time', 'end_time'])
            ->into('phinxlog')
            ->values([
                'version' => 20160415220805,
                'migration_name' => $versionParts[1],
                'start_time' => '2016-05-22 16:51:46',
                'end_time' => '2016-05-22 16:51:46',
            ])
            ->execute();
        $this->getMigrations('MigrationsDiffAddRemove')->rollback(['target' => 'all']);
    }

    /**
     * Get the baked filename based on the current db environment
     *
     * @param string $name Name of the baked file, unaware of the DB environment
     * @return string Baked filename
     */
    public function getBakeName($name)
    {
        $name .= ucfirst(getenv("DB"));

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
        $dbenv = getenv("DB");
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
     * @return string[]
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
