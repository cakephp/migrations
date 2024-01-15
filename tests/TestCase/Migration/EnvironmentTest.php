<?php
declare(strict_types=1);

namespace Test\Phinx\Migration;

use Migrations\Db\Adapter\AdapterFactory;
use Migrations\Migration\Environment;
use PDO;
use Phinx\Migration\MigrationInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

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
        $this->expectExceptionMessage('Adapter "fakeadapter" has not been registered');

        $this->environment->getAdapter();
    }

    public function testNoAdapter()
    {
        $this->expectException(RuntimeException::class);

        $this->environment->getAdapter();
    }

    private function getPdoMock()
    {
        $pdoMock = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
        $attributes = [];
        $pdoMock->method('setAttribute')->will($this->returnCallback(function ($attribute, $value) use (&$attributes) {
            $attributes[$attribute] = $value;

            return true;
        }));
        $pdoMock->method('getAttribute')->will($this->returnCallback(function ($attribute) use (&$attributes) {
            return $attributes[$attribute] ?? 'pdomock';
        }));

        return $pdoMock;
    }

    public function testGetAdapterWithExistingPdoInstance()
    {
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        $adapter = $this->getMockForAbstractClass('\Migrations\Db\Adapter\PdoAdapter', [['foo' => 'bar']]);
        AdapterFactory::instance()->registerAdapter('pdomock', $adapter);
        $this->environment->setOptions(['connection' => $this->getPdoMock()]);
        $options = $this->environment->getAdapter()->getOptions();
        $this->assertEquals('pdomock', $options['adapter']);
    }

    public function testSetPdoAttributeToErrmodeException()
    {
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        $adapter = $this->getMockForAbstractClass('\Migrations\Db\Adapter\PdoAdapter', [['foo' => 'bar']]);
        AdapterFactory::instance()->registerAdapter('pdomock', $adapter);
        $this->environment->setOptions(['connection' => $this->getPdoMock()]);
        $options = $this->environment->getAdapter()->getOptions();
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $options['connection']->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testGetAdapterWithBadExistingPdoInstance()
    {
        $this->environment->setOptions(['connection' => new stdClass()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The specified connection is not a PDO instance');

        $this->environment->getAdapter();
    }

    public function testSchemaName()
    {
        $this->assertEquals('phinxlog', $this->environment->getSchemaTableName());

        $this->environment->setSchemaTableName('changelog');
        $this->assertEquals('changelog', $this->environment->getSchemaTableName());
    }

    public function testCurrentVersion()
    {
        $stub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
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
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->addMethods(['up'])
            ->getMock();
        $upMigration->expects($this->once())
                    ->method('up');

        $this->markTestIncomplete('Requires a shim adapter to pass.');
        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
    }

    public function testExecutingAMigrationDown()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // down
        $downMigration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->addMethods(['down'])
            ->getMock();
        $downMigration->expects($this->once())
                      ->method('down');

        $this->markTestIncomplete('Requires a shim adapter to pass.');
        $this->environment->executeMigration($downMigration, MigrationInterface::DOWN);
    }

    public function testExecutingAMigrationWithTransactions()
    {
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
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
        $migration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->addMethods(['up'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('up');

        $this->environment->executeMigration($migration, MigrationInterface::UP);
    }

    public function testExecutingAChangeMigrationUp()
    {
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20130301080000'])
            ->addMethods(['change'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, MigrationInterface::UP);
    }

    public function testExecutingAChangeMigrationDown()
    {
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20130301080000'])
            ->addMethods(['change'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
    }

    public function testExecutingAFakeMigration()
    {
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
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
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->willReturn($adapterStub);

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
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
        $this->markTestIncomplete('Requires a shim adapter to pass.');
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();

        $this->environment->setAdapter($adapterStub);

        // up
        $seed = $this->getMockBuilder('\Migrations\AbstractSeed')
            ->onlyMethods(['run', 'init'])
            ->getMock();

        $seed->expects($this->once())
                    ->method('run');
        $seed->expects($this->once())
                    ->method('init');

        $this->environment->executeSeed($seed);
    }
}
