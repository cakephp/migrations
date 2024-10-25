<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use ReflectionProperty;

class SeedCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Migrations.backend', 'builtin');

        $table = $this->fetchTable('Phinxlog');
        try {
            $table->deleteAll('1=1');
        } catch (DatabaseException $e) {
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        $connection->execute('DROP TABLE IF EXISTS numbers');
        $connection->execute('DROP TABLE IF EXISTS letters');
        $connection->execute('DROP TABLE IF EXISTS stores');
    }

    protected function resetOutput(): void
    {
        if ($this->_out) {
            $property = new ReflectionProperty($this->_out, '_out');
            $property->setValue($this->_out, []);
        }
    }

    protected function createTables(): void
    {
        $this->exec('migrations migrate -c test -s TestsMigrations --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();
    }

    public function testHelp(): void
    {
        $this->exec('migrations seed --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('Seed the database with data');
        $this->assertOutputContains('migrations seed --connection secondary --seed UserSeeder');
    }

    public function testSeederEvents(): void
    {
        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeSeed', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        EventManager::instance()->on('Migration.afterSeed', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });

        $this->createTables();
        $this->exec('migrations seed -c test --seed NumbersSeed');
        $this->assertExitSuccess();

        $this->assertSame(['Migration.beforeSeed', 'Migration.afterSeed'], $fired);
    }

    public function testBeforeSeederAbort(): void
    {
        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeSeed', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
            $event->stopPropagation();
        });
        EventManager::instance()->on('Migration.afterSeed', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });

        $this->createTables();
        $this->exec('migrations seed -c test --seed NumbersSeed');
        $this->assertExitError();

        $this->assertSame(['Migration.beforeSeed'], $fired);
    }

    public function testSeederUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "NotThere" does not exist');
        $this->exec('migrations seed -c test --seed NotThere');
    }

    public function testSeederOne(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --seed NumbersSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederBaseSeed(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --source BaseSeeds --seed MigrationSeedNumbers');
        $this->assertExitSuccess();
        $this->assertOutputContains('MigrationSeedNumbers:</info> <comment>seeding');
        $this->assertOutputContains('AnotherNumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('radix=10');
        $this->assertOutputContains('fetchRow=121');
        $this->assertOutputContains('hasTable=1');
        $this->assertOutputContains('fetchAll=121');
        $this->assertOutputContains('All Done');

        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        // Two seeders run == 2 rows
        $this->assertEquals(2, $query->fetchColumn(0));
    }

    public function testSeederImplictAll(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test');

        $this->assertExitSuccess();
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederMultipleNotFound(): void
    {
        $this->createTables();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "NotThere" does not exist');
        $this->exec('migrations seed -c test --seed NumbersSeed --seed NotThere');
    }

    public function testSeederMultiple(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --source CallSeeds --seed LettersSeed --seed NumbersCallSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('NumbersCallSeed:</info> <comment>seeding');
        $this->assertOutputContains('LettersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));

        $query = $connection->execute('SELECT COUNT(*) FROM letters');
        $this->assertEquals(2, $query->fetchColumn(0));
    }

    public function testSeederSourceNotFound(): void
    {
        $this->createTables();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "LettersSeed" does not exist');

        $this->exec('migrations seed -c test --source NotThere --seed LettersSeed');
    }
}
