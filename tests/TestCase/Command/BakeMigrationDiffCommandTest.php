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

use Cake\Console\BaseCommand;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\Utility\Inflector;
use Migrations\Migrations;
use Migrations\Test\TestCase\TestCase;
use Phinx\Config\FeatureFlags;
use function Cake\Core\env;

/**
 * MigrationSnapshotTaskTest class
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

        $this->loadPlugins([
            'Migrations' => ['boostrap' => true],
        ]);
        $this->generatedFiles = [];
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        Configure::write('Migrations', []);
        FeatureFlags::$unsignedPrimaryKeys = true;
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
        $this->skipIf(getenv('DB_URL_COMPARE') === false);

        Configure::write('Migrations.unsigned_primary_keys', false);

        $this->runDiffBakingTest('WithAutoIdCompatibleSignedPrimaryKeys');
    }

    /**
     * Tests that baking a diff with signed primary keys is not auto-id compatible
     * when using the default settings.
     */
    public function testBakingDiffWithAutoIdIncompatibleSignedPrimaryKeys(): void
    {
        $this->skipIf(getenv('DB_URL_COMPARE') === false);

        $this->runDiffBakingTest('WithAutoIdIncompatibleSignedPrimaryKeys');
    }

    /**
     * Tests that baking a diff with unsigned primary keys is not auto-id compatible
     * when `Migrations.unsigned_primary_keys` is disabled.
     */
    public function testBakingDiffWithAutoIdIncompatibleUnsignedPrimaryKeys(): void
    {
        $this->skipIf(getenv('DB_URL_COMPARE') === false);

        Configure::write('Migrations.unsigned_primary_keys', false);

        $this->runDiffBakingTest('WithAutoIdIncompatibleUnsignedPrimaryKeys');
    }

    protected function runDiffBakingTest(string $scenario): void
    {
        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . lcfirst($scenario) . DS;
        $diffMigrationsPath = $diffConfigFolder . 'the_diff_' . Inflector::underscore($scenario) . '_' . env('DB') . '.php';
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        $destinationConfigDir = ROOT . DS . 'config' . DS . "MigrationsDiff{$scenario}" . DS;
        $destination = $destinationConfigDir . "20160415220805_TheDiff{$scenario}" . ucfirst(env('DB')) . '.php';
        $destinationDumpPath = $destinationConfigDir . 'schema-dump-test_comparisons_' . env('DB') . '.lock';
        copy($diffMigrationsPath, $destination);

        $this->generatedFiles = [
            $destination,
            $destinationDumpPath,
        ];

        $this->getMigrations("MigrationsDiff$scenario")->migrate();

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
     * Get the baked filename based on the current db environment
     *
     * @param string $name Name of the baked file, unaware of the DB environment
     * @return string Baked filename
     */
    public function getBakeName($name)
    {
        $name .= ucfirst(getenv('DB'));

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
