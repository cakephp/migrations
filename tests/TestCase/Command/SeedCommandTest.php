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
use Phinx\Config\FeatureFlags;
use ReflectionClass;
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

        if (class_exists(FeatureFlags::class)) {
            $reflection = new ReflectionClass(FeatureFlags::class);
            if ($reflection->hasProperty('addTimestampsUseDateTime')) {
                FeatureFlags::$addTimestampsUseDateTime = false;
            }
        }
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
        $this->assertOutputContains('migrations seed --connection secondary --seed UserSeed');
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
        $this->expectExceptionMessage('The seed `NotThere` does not exist');
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

    public function testSeederImplicitAll(): void
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
        $this->expectExceptionMessage('The seed `NotThere` does not exist');
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
        $this->expectExceptionMessage('The seed `LettersSeed` does not exist');

        $this->exec('migrations seed -c test --source NotThere --seed LettersSeed');
    }

    public function testSeederWithTimestampFields(): void
    {
        if (class_exists(FeatureFlags::class)) {
            $reflection = new ReflectionClass(FeatureFlags::class);
            if ($reflection->hasProperty('addTimestampsUseDateTime')) {
                FeatureFlags::$addTimestampsUseDateTime = false;
            }
        }

        $this->createTables();
        $this->exec('migrations seed -c test --seed StoresSeed');

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
        $this->assertNotEmpty($store['modified']);
    }

    public function testSeederWithDateTimeFields(): void
    {
        $this->skipIf(!class_exists(FeatureFlags::class));

        $reflection = new ReflectionClass(FeatureFlags::class);
        $this->skipIf(!$reflection->hasProperty('addTimestampsUseDateTime'));

        FeatureFlags::$addTimestampsUseDateTime = true;

        $this->createTables();
        $this->exec('migrations seed -c test --seed StoresSeed');

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
        $this->assertNotEmpty($store['modified']);
    }

    public function testDryRunModeWarning(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --seed NumbersSeed --dry-run');

        $this->assertExitSuccess();
        $this->assertErrorContains('<warning>dry-run mode enabled</warning>');
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testDryRunModeShortOption(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --seed NumbersSeed -x');

        $this->assertExitSuccess();
        $this->assertErrorContains('<warning>dry-run mode enabled</warning>');
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testDryRunModeNoDataChanges(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $initialCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);

        $this->exec('migrations seed -c test --seed NumbersSeed --dry-run');
        $this->assertExitSuccess();

        $finalCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);
        $this->assertEquals($initialCount, $finalCount, 'Dry-run mode should not modify database');
    }

    public function testDryRunModeMultipleSeeds(): void
    {
        $this->createTables();
        $this->exec('migrations seed -c test --source CallSeeds --seed LettersSeed --seed NumbersCallSeed --dry-run');

        $this->assertExitSuccess();
        $this->assertErrorContains('<warning>dry-run mode enabled</warning>');
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

        $this->exec('migrations seed -c test --dry-run');
        $this->assertExitSuccess();
        $this->assertErrorContains('<warning>dry-run mode enabled</warning>');
        $this->assertOutputContains('NumbersSeed:</info> <comment>seeding');

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
        $this->exec('migrations seed -c test --seed NumbersSeed --dry-run');
        $this->assertExitSuccess();
        $this->assertErrorContains('<warning>dry-run mode enabled</warning>');

        $this->assertSame(['Migration.beforeSeed', 'Migration.afterSeed'], $fired);
    }

    public function testDryRunModeWithStoresSeed(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $initialCount = $connection->execute('SELECT COUNT(*) FROM stores')->fetchColumn(0);

        $this->exec('migrations seed -c test --seed StoresSeed --dry-run');
        $this->assertExitSuccess();
        $this->assertErrorContains('<warning>dry-run mode enabled</warning>');
        $this->assertOutputContains('StoresSeed:</info> <comment>seeding');

        $finalCount = $connection->execute('SELECT COUNT(*) FROM stores')->fetchColumn(0);
        $this->assertEquals($initialCount, $finalCount, 'Dry-run mode should not modify stores table');
    }
}
