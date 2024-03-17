<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use RuntimeException;

class DumpCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected Connection $connection;
    protected string $_compareBasePath;
    protected string $dumpFile;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Migrations.backend', 'builtin');

        /** @var \Cake\Database\Connection $this->connection */
        $this->connection = ConnectionManager::get('test');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');
        $this->connection->execute('DROP TABLE IF EXISTS letters');
        $this->connection->execute('DROP TABLE IF EXISTS parts');
        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');

        $this->dumpFile = ROOT . DS . 'config/TestsMigrations/schema-dump-test.lock';
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->connection->execute('DROP TABLE IF EXISTS numbers');
        $this->connection->execute('DROP TABLE IF EXISTS letters');
        $this->connection->execute('DROP TABLE IF EXISTS parts');
        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        if (file_exists($this->dumpFile)) {
            unlink($this->dumpFile);
        }
    }

    public function testExecuteIncorrectConnection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->exec('migrations dump --connection lolnope');
    }

    public function testExecuteIncorrectPlugin(): void
    {
        $this->expectException(MissingPluginException::class);
        $this->exec('migrations dump --plugin lolnope');
    }

    public function testExecuteSuccess(): void
    {
        // Run migrations
        $this->exec('migrations migrate --connection test --source TestsMigrations --no-lock');
        $this->assertExitSuccess();

        // Generate dump file.
        $this->exec('migrations dump --connection test --source TestsMigrations');

        $this->assertExitSuccess();
        $this->assertOutputContains('config/TestsMigrations/schema-dump-test.lock');

        $this->assertFileExists($this->dumpFile);
        /** @var array<string, TableSchema> $generatedDump */
        $generatedDump = unserialize(file_get_contents($this->dumpFile));

        $this->assertArrayHasKey('letters', $generatedDump);
        $this->assertArrayHasKey('numbers', $generatedDump);
        $this->assertInstanceOf(TableSchema::class, $generatedDump['numbers']);
        $this->assertInstanceOf(TableSchema::class, $generatedDump['letters']);
        $this->assertEquals(['id', 'number', 'radix'], $generatedDump['numbers']->columns());
        $this->assertEquals(['id', 'letter'], $generatedDump['letters']->columns());
    }

    public function testExecutePlugin(): void
    {
        $this->loadPlugins(['Migrator']);

        $this->exec('migrations dump --connection test --plugin Migrator');

        $this->assertExitSuccess();
        $this->assertOutputContains('Migrator/config/Migrations/schema-dump-test.lock');

        $dumpFile = Plugin::path('Migrator') . '/config/Migrations/schema-dump-test.lock';
        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }
    }
}
