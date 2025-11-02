<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class SeedCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();

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

    protected function createTables(): void
    {
        $this->exec('migrations migrate -c test -s TestsMigrations --no-lock');
        $this->assertExitSuccess();
        $this->_in = null;
    }

    public function testHelp(): void
    {
        $this->exec('migrations seed --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('Seed the database with data');
        $this->assertOutputContains('migrations seed Posts');
        $this->assertOutputContains('migrations seed Users,Posts');
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
        $this->exec('migrations seed -c test NumbersSeed');
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
        $this->exec('migrations seed -c test NumbersSeed');
        $this->assertExitError();

        $this->assertSame(['Migration.beforeSeed'], $fired);
    }

    public function testSeederUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed `NotThere` does not exist');
        $this->exec('migrations seed -c test NotThere');
    }

    public function testSeederOne(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test NumbersSeed');

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
        $this->exec('migrations seed -c test --source BaseSeeds MigrationSeedNumbers');
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

    public function testSeederImplicitAll(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test -q');

        $this->assertExitSuccess();
        $this->assertOutputNotContains('The following seeds will be executed:');
        $this->assertOutputNotContains('Do you want to continue?');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederMultipleNotFound(): void
    {
        $this->createTables();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed `NotThere` does not exist');
        $this->exec('migrations seed -c test NumbersSeed,NotThere');
    }

    public function testSeederMultiple(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --source CallSeeds LettersSeed,NumbersCallSeed');

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
        $this->expectExceptionMessage('The seed `LettersSeed` does not exist');

        $this->exec('migrations seed -c test --source NotThere LettersSeed');
    }

    public function testSeederWithTimestampFields(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test StoresSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('StoresSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $result = $connection->selectQuery()
            ->select(['*'])
            ->from('stores')
            ->orderBy('id DESC')
            ->limit(1)
            ->execute()->fetchAll('assoc');

        $this->assertNotEmpty($result[0]);
        $store = $result[0];
        $this->assertEquals('foo_with_date', $store['name']);
        $this->assertNotEmpty($store['created']);
        $this->assertNotEmpty($store['updated']);
    }

    public function testDryRunModeWarning(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test NumbersSeed --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testDryRunModeShortOption(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test NumbersSeed -d');

        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testDryRunModeNoDataChanges(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $initialCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);

        $this->exec('migrations seed -c test NumbersSeed --dry-run');
        $this->assertExitSuccess();

        $finalCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);
        $this->assertEquals($initialCount, $finalCount, 'Dry-run mode should not modify database');
    }

    public function testDryRunModeMultipleSeeds(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --source CallSeeds LettersSeed,NumbersCallSeed --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('NumbersCallSeed:</info> <comment>seeding');
        $this->assertOutputContains('LettersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $numbersCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);
        $lettersCount = $connection->execute('SELECT COUNT(*) FROM letters')->fetchColumn(0);

        $this->assertEquals(0, $numbersCount, 'Dry-run mode should not insert into numbers table');
        $this->assertEquals(0, $lettersCount, 'Dry-run mode should not insert into letters table');
    }

    public function testDryRunModeAllSeeds(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $initialCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);

        $this->exec('migrations seed -c test --dry-run -q');
        $this->assertExitSuccess();

        $finalCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);
        $this->assertEquals($initialCount, $finalCount, 'Dry-run mode should not modify database when running all seeds');
    }

    public function testDryRunModeWithEvents(): void
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
        $this->exec('migrations seed -c test NumbersSeed --dry-run');
        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');

        $this->assertSame(['Migration.beforeSeed', 'Migration.afterSeed'], $fired);
    }

    public function testDryRunModeWithStoresSeed(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $initialCount = $connection->execute('SELECT COUNT(*) FROM stores')->fetchColumn(0);

        $this->exec('migrations seed -c test StoresSeed --dry-run');
        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('StoresSeed:</info> <comment>seeding');

        $finalCount = $connection->execute('SELECT COUNT(*) FROM stores')->fetchColumn(0);
        $this->assertEquals($initialCount, $finalCount, 'Dry-run mode should not modify stores table');
    }

    public function testSeederAnonymousClass(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test AnonymousStoreSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('AnonymousStoreSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM stores');
        $this->assertEquals(2, $query->fetchColumn(0));

        $result = $connection->execute('SELECT * FROM stores ORDER BY id')->fetchAll('assoc');
        $this->assertEquals('anonymous_store', $result[0]['name']);
        $this->assertEquals('other_store', $result[1]['name']);
    }

    public function testSeederShortName(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test Numbers');

        $this->assertExitSuccess();
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederShortNameMultiple(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --source CallSeeds Letters,NumbersCall');

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

    public function testSeederShortNameAnonymous(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test AnonymousStore');

        $this->assertExitSuccess();
        $this->assertOutputContains('AnonymousStoreSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM stores');
        $this->assertEquals(2, $query->fetchColumn(0));
    }

    public function testSeederAllWithQuietModeSkipsConfirmation(): void
    {
        $this->createTables();
        // Quiet mode should skip confirmation prompt
        $this->exec('migrations seed -c test -q');

        $this->assertExitSuccess();
        $this->assertOutputNotContains('The following seeds will be executed:');
        $this->assertOutputNotContains('Do you want to continue?');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederAllHasConfirmation(): void
    {
        $this->createTables();
        // Confirm run all.
        $this->exec('migrations seed -c test', ['y']);

        $this->assertExitSuccess();
        $this->assertOutputContains('The following seeds will be executed:');
        $this->assertOutputContains('Do you want to continue?');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederSpecificSeedSkipsConfirmation(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test NumbersSeed');

        $this->assertExitSuccess();
        $this->assertOutputNotContains('The following seeds will be executed:');
        $this->assertOutputNotContains('Do you want to continue?');
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testSeederCommaSeparated(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --source CallSeeds Letters,NumbersCall');

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
}
