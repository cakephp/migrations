<?php
declare(strict_types=1);

namespace Test\Phinx\Migration;

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
            'name' => $config['database'],
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
             ->will($this->returnValue([20110301080000]));

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
        $upMigration = $this->getMockBuilder(AbstractMigration::class)
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->addMethods(['up'])
            ->getMock();
        $upMigration->expects($this->once())
                    ->method('up');

        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
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
        $downMigration = $this->getMockBuilder(AbstractMigration::class)
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->addMethods(['down'])
            ->getMock();
        $downMigration->expects($this->once())
                      ->method('down');

        $this->environment->executeMigration($downMigration, MigrationInterface::DOWN);
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
                    ->will($this->returnValue(true));

        $this->environment->setAdapter($adapterStub);

        // migrate
        $migration = $this->getMockBuilder(AbstractMigration::class)
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->addMethods(['up'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('up');

        $this->environment->executeMigration($migration, MigrationInterface::UP);
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
        $migration = $this->getMockBuilder(AbstractMigration::class)
            ->setConstructorArgs(['mockenv', '20130301080000'])
            ->addMethods(['change'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, MigrationInterface::UP);
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
        $migration = $this->getMockBuilder(AbstractMigration::class)
            ->setConstructorArgs(['mockenv', '20130301080000'])
            ->addMethods(['change'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
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
        $migration = $this->getMockBuilder(AbstractMigration::class)
            ->setConstructorArgs(['mockenv', '20130301080000'])
            ->addMethods(['change'])
            ->getMock();
        $migration->expects($this->never())
                  ->method('change');

        $this->environment->executeMigration($migration, MigrationInterface::UP, true);
    }

    public function testGettingInputObject()
    {
        $mock = $this->getMockBuilder('\Symfony\Component\Console\Input\InputInterface')
            ->getMock();
        $this->environment->setInput($mock);
        $inputObject = $this->environment->getInput();
        $this->assertInstanceOf('\Symfony\Component\Console\Input\InputInterface', $inputObject);
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
        $upMigration = $this->getMockBuilder(AbstractMigration::class)
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->addMethods(['up', 'init'])
            ->getMock();
        $upMigration->expects($this->once())
                    ->method('up');
        $upMigration->expects($this->once())
                    ->method('init');

        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
    }

    public function testExecuteSeedInit()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder(PdoAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $this->environment->setAdapter($adapterStub);

        // up
        $seed = $this->getMockBuilder(AbstractSeed::class)
            ->addMethods(['init'])
            ->onlyMethods(['run'])
            ->getMock();

        $seed->expects($this->once())
                    ->method('run');
        $seed->expects($this->once())
                    ->method('init');

        $this->environment->executeSeed($seed);
    }
}
