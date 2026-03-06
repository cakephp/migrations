<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use InvalidArgumentException;
use Migrations\Test\TestCase\TestCase;

class SeedCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->clearMigrationRecords('test');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        $connection->execute('DROP TABLE IF EXISTS numbers');
        $connection->execute('DROP TABLE IF EXISTS letters');
        $connection->execute('DROP TABLE IF EXISTS stores');
        $connection->execute('DROP TABLE IF EXISTS cake_seeds');
    }

    protected function createTables(): void
    {
        $this->exec('migrations migrate -c test -s TestsMigrations --no-lock');
        $this->assertExitSuccess();
        $this->_in = null;
    }

    public function testHelp(): void
    {
        $this->exec('seeds run --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('Seed the database with data');
        $this->assertOutputContains('seeds run Posts');
        $this->assertOutputContains('seeds run Users,Posts');
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
        $this->exec('seeds run -c test NumbersSeed');
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
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitError();

        $this->assertSame(['Migration.beforeSeed'], $fired);
    }

    public function testSeederUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed `NotThere` does not exist');
        $this->exec('seeds run -c test NotThere');
    }

    public function testSeederOne(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test NumbersSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('Numbers seed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederBaseSeed(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test --source BaseSeeds MigrationSeedNumbers');
        $this->assertExitSuccess();
        $this->assertOutputContains('MigrationSeedNumbers seed:</info> <comment>seeding');
        $this->assertOutputContains('AnotherNumbers seed:</info> <comment>seeding');
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
        $this->exec('seeds run -c test -q');

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
        $this->exec('seeds run -c test NumbersSeed,NotThere');
    }

    public function testSeederMultiple(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test --source CallSeeds LettersSeed,NumbersCallSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('NumbersCall seed:</info> <comment>seeding');
        $this->assertOutputContains('Letters seed:</info> <comment>seeding');
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

        $this->exec('seeds run -c test --source NotThere LettersSeed');
    }

    public function testSeederWithTimestampFields(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test StoresSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('Stores seed:</info> <comment>seeding');
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
        $this->exec('seeds run -c test NumbersSeed --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('Numbers seed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testDryRunModeShortOption(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test NumbersSeed -d');

        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('Numbers seed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testDryRunModeNoDataChanges(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $initialCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);

        $this->exec('seeds run -c test NumbersSeed --dry-run');
        $this->assertExitSuccess();

        $finalCount = $connection->execute('SELECT COUNT(*) FROM numbers')->fetchColumn(0);
        $this->assertEquals($initialCount, $finalCount, 'Dry-run mode should not modify database');
    }

    public function testDryRunModeMultipleSeeds(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test --source CallSeeds LettersSeed,NumbersCallSeed --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('NumbersCall seed:</info> <comment>seeding');
        $this->assertOutputContains('Letters seed:</info> <comment>seeding');
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

        $this->exec('seeds run -c test --dry-run -q');
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
        $this->exec('seeds run -c test NumbersSeed --dry-run');
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

        $this->exec('seeds run -c test StoresSeed --dry-run');
        $this->assertExitSuccess();
        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('Stores seed:</info> <comment>seeding');

        $finalCount = $connection->execute('SELECT COUNT(*) FROM stores')->fetchColumn(0);
        $this->assertEquals($initialCount, $finalCount, 'Dry-run mode should not modify stores table');
    }

    public function testSeederAnonymousClass(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test AnonymousStoreSeed');

        $this->assertExitSuccess();
        $this->assertOutputContains('AnonymousStore seed:</info> <comment>seeding');
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
        $this->exec('seeds run -c test Numbers');

        $this->assertExitSuccess();
        $this->assertOutputContains('Numbers seed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testSeederShortNameMultiple(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test --source CallSeeds Letters,NumbersCall');

        $this->assertExitSuccess();
        $this->assertOutputContains('NumbersCall seed:</info> <comment>seeding');
        $this->assertOutputContains('Letters seed:</info> <comment>seeding');
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
        $this->exec('seeds run -c test AnonymousStore');

        $this->assertExitSuccess();
        $this->assertOutputContains('AnonymousStore seed:</info> <comment>seeding');
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
        $this->exec('seeds run -c test -q');

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
        $this->exec('seeds run -c test', ['y']);

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
        $this->exec('seeds run -c test NumbersSeed');

        $this->assertExitSuccess();
        $this->assertOutputNotContains('The following seeds will be executed:');
        $this->assertOutputNotContains('Do you want to continue?');
        $this->assertOutputContains('Numbers seed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');
    }

    public function testSeederCommaSeparated(): void
    {
        $this->createTables();
        $this->exec('seeds run -c test --source CallSeeds Letters,NumbersCall');

        $this->assertExitSuccess();
        $this->assertOutputContains('NumbersCall seed:</info> <comment>seeding');
        $this->assertOutputContains('Letters seed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));

        $query = $connection->execute('SELECT COUNT(*) FROM letters');
        $this->assertEquals(2, $query->fetchColumn(0));
    }

    public function testSeedStateTracking(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // First run should execute the seed
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();
        $this->assertOutputContains('Numbers seed:</info> <comment>seeding');
        $this->assertOutputContains('All Done');

        // Verify data was inserted
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));

        // Second run should silently skip the seed (already executed)
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();
        $this->assertOutputNotContains('seeding');

        // Verify no additional data was inserted
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));

        // Run with --force should re-execute
        $this->exec('seeds run -c test NumbersSeed --force');
        $this->assertExitSuccess();
        $this->assertOutputContains('Numbers seed:</info> <comment>seeding');

        // Verify data was inserted again (now 2 records)
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(2, $query->fetchColumn(0));
    }

    public function testSeedStatusCommand(): void
    {
        $this->createTables();

        // Check status before running seeds
        $this->exec('seeds status -c test');
        $this->assertExitSuccess();
        $this->assertOutputContains('Current seed execution status:');
        $this->assertOutputContains('pending');

        // Run a seed
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();

        // Check status after running seed
        $this->exec('seeds status -c test');
        $this->assertExitSuccess();
        $this->assertOutputContains('executed');
        $this->assertOutputContains('Numbers');
    }

    public function testSeedResetCommand(): void
    {
        $this->createTables();

        // Run a seed
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();
        $this->assertOutputContains('seeding');

        // Reset the seed
        $this->exec('seeds reset -c test', ['y']);
        $this->assertExitSuccess();
        $this->assertOutputContains('All seeds will be reset:');

        // Verify seed can be run again without --force
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();
        $this->assertOutputContains('seeding');
        $this->assertOutputNotContains('already executed');
    }

    public function testIdempotentSeed(): void
    {
        $this->createTables();

        // First run - should insert data
        $this->exec('seeds run -c test -s TestSeeds IdempotentTest');
        $this->assertExitSuccess();
        $this->assertOutputContains('seeding');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $query = $connection->execute('SELECT COUNT(*) FROM numbers WHERE number = 99');
        $this->assertEquals(1, $query->fetchColumn(0));

        // Second run - should run again (not skip) and insert another row
        $this->exec('seeds run -c test -s TestSeeds IdempotentTest');
        $this->assertExitSuccess();
        $this->assertOutputContains('seeding');
        $this->assertOutputNotContains('already executed');

        // Verify it ran again and inserted another row
        $query = $connection->execute('SELECT COUNT(*) FROM numbers WHERE number = 99');
        $this->assertEquals(2, $query->fetchColumn(0));

        // Verify the seed WAS tracked in cake_seeds table (only one record, updated each run)
        $seedLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'IdempotentTestSeed\'');
        $this->assertEquals(1, $seedLog->fetchColumn(0), 'Idempotent seeds should track last execution');
    }

    public function testNonIdempotentSeedIsTracked(): void
    {
        $this->createTables();

        // Run a regular (non-idempotent) seed
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();
        $this->assertOutputContains('seeding');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // Verify the seed WAS tracked in cake_seeds table
        $seedLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'NumbersSeed\'');
        $this->assertEquals(1, $seedLog->fetchColumn(0), 'Regular seeds should be tracked');

        // Run again - should be silently skipped
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();
        $this->assertOutputNotContains('seeding');
    }

    public function testFakeSeedMarksAsExecuted(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // Run with --fake flag
        $this->exec('seeds run -c test NumbersSeed --fake');
        $this->assertExitSuccess();
        $this->assertErrorContains('performing fake seeding');
        $this->assertOutputContains('faking');
        $this->assertOutputContains('faked');
        $this->assertOutputNotContains('seeding');

        // Verify NO data was inserted
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(0, $query->fetchColumn(0), 'Fake seed should not insert data');

        // Verify the seed WAS tracked in cake_seeds table
        $seedLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'NumbersSeed\'');
        $this->assertEquals(1, $seedLog->fetchColumn(0), 'Fake seeds should be tracked');

        // Running again should be silently skipped
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();
        $this->assertOutputNotContains('seeding');
    }

    public function testFakeSeedWithForce(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // Run with --fake first
        $this->exec('seeds run -c test NumbersSeed --fake');
        $this->assertExitSuccess();

        // Verify seed is tracked
        $seedLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'NumbersSeed\'');
        $this->assertEquals(1, $seedLog->fetchColumn(0));

        // Run with --force to actually execute it
        $this->exec('seeds run -c test NumbersSeed --force');
        $this->assertExitSuccess();
        $this->assertOutputContains('seeding');

        // Verify data was inserted
        $query = $connection->execute('SELECT COUNT(*) FROM numbers');
        $this->assertEquals(1, $query->fetchColumn(0));
    }

    public function testResetSpecificSeed(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // Run two seeds
        $this->exec('seeds run -c test NumbersSeed');
        $this->assertExitSuccess();

        $this->exec('seeds run -c test StoresSeed');
        $this->assertExitSuccess();

        // Verify both are tracked
        $numbersLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'NumbersSeed\'');
        $this->assertEquals(1, $numbersLog->fetchColumn(0));

        $storesLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'StoresSeed\'');
        $this->assertEquals(1, $storesLog->fetchColumn(0));

        // Reset only Numbers seed
        $this->exec('seeds reset -c test --seed Numbers', ['y']);
        $this->assertExitSuccess();
        $this->assertOutputContains('The following seeds will be reset:');
        $this->assertOutputNotContains('All seeds will be reset:');

        // Verify Numbers is reset but Stores is still tracked
        $numbersLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'NumbersSeed\'');
        $this->assertEquals(0, $numbersLog->fetchColumn(0), 'Numbers seed should be reset');

        $storesLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'StoresSeed\'');
        $this->assertEquals(1, $storesLog->fetchColumn(0), 'Stores seed should still be tracked');
    }

    public function testResetMultipleSpecificSeeds(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // Run seeds
        $this->exec('seeds run -c test NumbersSeed');
        $this->exec('seeds run -c test StoresSeed');

        // Reset both with comma-separated list
        $this->exec('seeds reset -c test --seed Numbers,Stores', ['y']);
        $this->assertExitSuccess();

        // Verify both are reset
        $numbersLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'NumbersSeed\'');
        $this->assertEquals(0, $numbersLog->fetchColumn(0));

        $storesLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'StoresSeed\'');
        $this->assertEquals(0, $storesLog->fetchColumn(0));
    }

    public function testResetNonExistentSeed(): void
    {
        $this->createTables();

        $this->exec('seeds reset -c test --seed NonExistent');
        $this->assertExitError();
        $this->assertErrorContains('Seed `NonExistent` does not exist');
    }

    public function testFakeIdempotentSeedIsTracked(): void
    {
        $this->createTables();

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // Run idempotent seed with --fake flag
        $this->exec('seeds run -c test -s TestSeeds IdempotentTest --fake');
        $this->assertExitSuccess();
        $this->assertOutputContains('faking');
        $this->assertOutputContains('faked');

        // Verify NO data was inserted
        $query = $connection->execute('SELECT COUNT(*) FROM numbers WHERE number = 99');
        $this->assertEquals(0, $query->fetchColumn(0), 'Fake seed should not insert data');

        // Verify the seed WAS tracked
        $seedLog = $connection->execute('SELECT COUNT(*) FROM cake_seeds WHERE seed_name = \'IdempotentTestSeed\'');
        $this->assertEquals(1, $seedLog->fetchColumn(0), 'Idempotent seeds should be tracked when faked');
    }
}
