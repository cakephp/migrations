<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Migration;

use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Migrations\BaseMigration;
use Migrations\BaseSeed;
use Migrations\Db\Adapter\AbstractAdapter;
use Migrations\Db\Adapter\AdapterWrapper;
use Migrations\DirectionalMigrationInterface;
use Migrations\Migration\Environment;
use Migrations\MigrationInterface;
use Migrations\ReversibleMigrationInterface;
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

    public function testConstructorWorksAsExpected(): void
    {
        $env = new Environment('testenv', ['foo' => 'bar']);
        $this->assertEquals('testenv', $env->getName());
        $this->assertArrayHasKey('foo', $env->getOptions());
    }

    public function testSettingTheName(): void
    {
        $this->environment->setName('prod123');
        $this->assertEquals('prod123', $this->environment->getName());
    }

    public function testSettingOptions(): void
    {
        $this->environment->setOptions(['foo' => 'bar']);
        $this->assertArrayHasKey('foo', $this->environment->getOptions());
    }

    public function testInvalidAdapter(): void
    {
        $this->environment->setOptions(['adapter' => 'fakeadapter']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No connection defined');

        $this->environment->getAdapter();
    }

    public function testNoAdapter(): void
    {
        $this->expectException(RuntimeException::class);

        $this->environment->getAdapter();
    }

    public function testGetAdapterWithBadConnectionName(): void
    {
        $this->environment->setOptions(['connection' => 'lolnope']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The datasource configuration `lolnope` was not found');

        $this->environment->getAdapter();
    }

    public function testGetAdapter(): void
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

    public function testSchemaName(): void
    {
        $this->assertEquals('phinxlog', $this->environment->getSchemaTableName());

        $this->environment->setSchemaTableName('changelog');
        $this->assertEquals('changelog', $this->environment->getSchemaTableName());
    }

    public function testCurrentVersion(): void
    {
        $stub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $stub->expects($this->any())
             ->method('getVersions')
             ->willReturn([20110301080000]);

        $this->environment->setAdapter($stub);

        $this->assertEquals(20110301080000, $this->environment->getCurrentVersion());
    }

    public function testExecutingAMigrationUp(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = new class (20110301080000) extends BaseMigration {
            public bool $executed = false;

            public function up(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
        $this->assertTrue($upMigration->executed);
    }

    public function testExecutingAMigrationDown(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // down
        $downMigration = new class (20110301080000) extends BaseMigration {
            public bool $executed = false;

            public function down(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($downMigration, MigrationInterface::DOWN);
        $this->assertTrue($downMigration->executed);
    }

    public function testExecutingAMigrationWithTransactions(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('beginTransaction');

        $adapterStub->expects($this->once())
                    ->method('commitTransaction');

        $adapterStub->expects($this->atLeastOnce())
                    ->method('hasTransactions')
                    ->willReturn(true);

        $this->environment->setAdapter($adapterStub);

        // migrate
        $migration = new class (20110301080000) extends BaseMigration {
            public bool $executed = false;

            public function up(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAMigrationWithUseTransactions(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->never())
                    ->method('beginTransaction');

        $adapterStub->expects($this->never())
                    ->method('commitTransaction');

        $adapterStub->method('hasTransactions')
                    ->willReturn(true);

        $this->environment->setAdapter($adapterStub);

        // migrate
        $migration = new class (20110301080000) extends BaseMigration {
            public bool $executed = false;

            public function useTransactions(): bool
            {
                return false;
            }

            public function up(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAChangeMigrationUp(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = new class (20130301080000) extends BaseMigration {
            public bool $executed = false;

            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAChangeMigrationDown(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = new class (20130301080000) extends BaseMigration {
            public bool $executed = false;

            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAReversibleInterfaceMigrationUp(): void
    {
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
            ->method('migrated')
            ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        $migration = new class (20260513120000) extends BaseMigration implements ReversibleMigrationInterface {
            public bool $executed = false;

            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingAReversibleInterfaceMigrationDown(): void
    {
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
            ->method('migrated')
            ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        $migration = new class (20260513120000) extends BaseMigration implements ReversibleMigrationInterface {
            public bool $executed = false;

            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
        $this->assertTrue($migration->executed);
    }

    public function testExecutingADirectionalInterfaceMigrationUp(): void
    {
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
            ->method('migrated')
            ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        $migration = new class (20260513120000) extends BaseMigration implements DirectionalMigrationInterface {
            public bool $upExecuted = false;

            public bool $downExecuted = false;

            public function up(): void
            {
                $this->upExecuted = true;
            }

            public function down(): void
            {
                $this->downExecuted = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->upExecuted);
        $this->assertFalse($migration->downExecuted);
    }

    public function testExecutingADirectionalInterfaceMigrationDown(): void
    {
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
            ->method('migrated')
            ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        $migration = new class (20260513120000) extends BaseMigration implements DirectionalMigrationInterface {
            public bool $upExecuted = false;

            public bool $downExecuted = false;

            public function up(): void
            {
                $this->upExecuted = true;
            }

            public function down(): void
            {
                $this->downExecuted = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
        $this->assertTrue($migration->downExecuted);
        $this->assertFalse($migration->upExecuted);
    }

    /**
     * If a class declares DirectionalMigrationInterface, dispatch must use
     * up()/down() even if the class happens to define a change() method —
     * the interface declaration wins over the legacy method_exists check.
     */
    public function testDirectionalInterfaceWinsOverChangeMethod(): void
    {
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
            ->method('migrated')
            ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        $migration = new class (20260513120000) extends BaseMigration implements DirectionalMigrationInterface {
            public bool $upExecuted = false;

            public bool $changeExecuted = false;

            public function up(): void
            {
                $this->upExecuted = true;
            }

            public function down(): void
            {
            }

            public function change(): void
            {
                $this->changeExecuted = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP);
        $this->assertTrue($migration->upExecuted);
        $this->assertFalse($migration->changeExecuted);
    }

    public function testExecutingAFakeMigration(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = new class (20130301080000) extends BaseMigration {
            public bool $executed = false;

            public function change(): void
            {
                $this->executed = true;
            }
        };

        $this->environment->executeMigration($migration, MigrationInterface::UP, true);
        $this->assertFalse($migration->executed);
    }

    public function testGettingInputObject(): void
    {
        $mock = $this->getMockBuilder(ConsoleIo::class)->getMock();
        $this->environment->setIo($mock);
        $inputObject = $this->environment->getIo();
        $this->assertInstanceOf(ConsoleIo::class, $inputObject);
    }

    public function testExecuteMigrationCallsInit(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = new class (20110301080000) extends BaseMigration {
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

    public function testExecuteSeedInit(): void
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $this->environment->setAdapter($adapterStub);

        $seed = new class (20110301080000) extends BaseSeed {
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
