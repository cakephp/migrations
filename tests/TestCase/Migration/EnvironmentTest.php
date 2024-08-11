<?php
declare(strict_types=1);

namespace Test\Phinx\Migration;

use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Migrations\Db\Adapter\AdapterWrapper;
use Migrations\Db\Adapter\PdoAdapter;
use Migrations\Migration\Environment;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\MigrationInterface;
use Phinx\Seed\AbstractSeed;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EnvironmentTest extends TestCase
{
    /**
     * @var \Migrations\Migration\Environment
     */
    protected $environment;

    protected function setUp(): void
    {
        $this->environment = new Environment('test', []);
    }

    public function testConstructorWorksAsExpected()
    {
        $env = new Environment('testenv', ['foo' => 'bar']);
        $this->assertEquals('testenv', $env->getName());
        $this->assertArrayHasKey('foo', $env->getOptions());
    }

    public function testSettingTheName()
    {
        $this->environment->setName('prod123');
        $this->assertEquals('prod123', $this->environment->getName());
    }

    public function testSettingOptions()
    {
        $this->environment->setOptions(['foo' => 'bar']);
        $this->assertArrayHasKey('foo', $this->environment->getOptions());
    }

    public function testInvalidAdapter()
    {
        $this->environment->setOptions(['adapter' => 'fakeadapter']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No connection defined');

        $this->environment->getAdapter();
    }

    public function testNoAdapter()
    {
        $this->expectException(RuntimeException::class);

        $this->environment->getAdapter();
    }

    public function testGetAdapterWithBadConnectionName()
    {
        $this->environment->setOptions(['connection' => 'lolnope']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The datasource configuration `lolnope` was not found');

        $this->environment->getAdapter();
    }

    public function testGetAdapter()
    {
        /** @var array<string, mixed> $config */
        $config = ConnectionManager::getConfig('test');
        $environment = new Environment('default', [
            'connection' => 'test',
            'database' => $config['database'],
            'migration_table' => 'phinxlog',
        ]);
        $adapter = $environment->getAdapter();
        $this->assertNotEmpty($adapter);
        $this->assertInstanceOf(AdapterWrapper::class, $adapter);
    }

    public function testSchemaName()
    {
        $this->assertEquals('phinxlog', $this->environment->getSchemaTableName());

        $this->environment->setSchemaTableName('changelog');
        $this->assertEquals('changelog', $this->environment->getSchemaTableName());
    }

    public function testCurrentVersion()
    {
        $stub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $stub->expects($this->any())
             ->method('getVersions')
             ->willReturn([20110301080000]);

        $this->environment->setAdapter($stub);

        $this->assertEquals(20110301080000, $this->environment->getCurrentVersion());
    }

    public function testExecutingAMigrationUp()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = new class ('mockenv', 20110301080000) extends AbstractMigration {
            public bool $executed = false;
            public function up(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
        $this->assertTrue($upMigration->executed);
    }

    public function testExecutingAMigrationDown()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // down
        $downMigration = new class ('mockenv', 20110301080000) extends AbstractMigration {
            public bool $executed = false;
            public function down(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($downMigration, MigrationInterface::DOWN);
        $this->assertTrue($downMigration->executed);
    }

    public function testExecutingAMigrationWithTransactions()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('beginTransaction');

        $adapterStub->expects($this->once())
                    ->method('commitTransaction');

        $adapterStub->expects($this->exactly(2))
                    ->method('hasTransactions')
                    ->willReturn(true);

        $this->environment->setAdapter($adapterStub);

        // migrate
        $migration = new class ('mockenv', 20110301080000) extends AbstractMigration {
            public bool $executed = false;
            public function up(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAChangeMigrationUp()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = new class ('mockenv', 20130301080000) extends AbstractMigration {
            public bool $executed = false;
            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAChangeMigrationDown()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = new class ('mockenv', 20130301080000) extends AbstractMigration {
            public bool $executed = false;
            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAFakeMigration()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = new class ('mockenv', 20130301080000) extends AbstractMigration {
            public bool $executed = false;
            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP, true);
        $this->assertFalse($migration->executed);
    }

    public function testGettingInputObject()
    {
        $mock = $this->getMockBuilder(ConsoleIo::class)->getMock();
        $this->environment->setIo($mock);
        $inputObject = $this->environment->getIo();
        $this->assertInstanceOf(ConsoleIo::class, $inputObject);
    }

    public function testExecuteMigrationCallsInit()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = new class ('mockenv', 20110301080000) extends AbstractMigration {
            public bool $initExecuted = false;
            public bool $upExecuted = false;

            public function init(): void
            {
                $this->initExecuted = true;
            }

            public function up(): void
            {
                $this->upExecuted = true;
            }
        };

        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
        $this->assertTrue($upMigration->initExecuted);
        $this->assertTrue($upMigration->upExecuted);
    }

    public function testExecuteSeedInit()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $this->environment->setAdapter($adapterStub);

        // up
        $seed = new class ('mockenv', 20110301080000) extends AbstractSeed {
            public bool $initExecuted = false;
            public bool $runExecuted = false;

            public function init(): void
            {
                $this->initExecuted = true;
            }

            public function run(): void
            {
                $this->runExecuted = true;
            }
        };

        $this->environment->executeSeed($seed);
        $this->assertTrue($seed->initExecuted);
        $this->assertTrue($seed->runExecuted);
    }
}
