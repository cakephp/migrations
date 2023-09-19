<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command\Phinx;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Migrations\CakeManager;
use Migrations\MigrationsDispatcher;
use Migrations\Test\CommandTester;
use Migrations\Test\TestCase\DriverConnectionTrait;
use Phinx\Db\Adapter\WrapperInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

class CreateTest extends TestCase
{
    use DriverConnectionTrait;
    use StringCompareTrait;

    /**
     * Instance of a Symfony Command object
     *
     * @var \Symfony\Component\Console\Command\Command|\Phinx\Console\Command\AbstractCommand
     */
    protected $command;

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $connection;

    /**
     * Instance of a StreamOutput object.
     * It will store the output from the CommandTester
     *
     * @var \Symfony\Component\Console\Output\StreamOutput
     */
    protected $streamOutput;

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

        $this->connection = ConnectionManager::get('test');
        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('create');
        $this->streamOutput = new StreamOutput(fopen('php://memory', 'w', false));
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Create' . DS;
        $this->generatedFiles = [];
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Test executing the `create` command will generate the desired file.
     *
     * @return void
     */
    public function testExecute()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'Create',
            'name' => 'TestCreateChange',
        ];
        $commandTester = $this->getCommandTester($params);

        $commandTester->execute([
            'command' => $this->command->getName(),
            'name' => 'TestCreateChange',
            '--connection' => 'test',
        ]);

        $files = glob(ROOT . DS . 'config' . DS . 'Create' . DS . '*_TestCreateChange*.php');
        $this->generatedFiles = $files;
        $this->assertNotEmpty($files);

        $file = current($files);
        $this->assertSameAsFile('TestCreateChange.php', file_get_contents($file));
    }

    /**
     * Gets a pre-configured of a CommandTester object that is initialized for each
     * test methods. This is needed in order to define the same PDO connection resource
     * between every objects needed during the tests.
     * This is mandatory for the SQLite database vendor, so phinx objects interacting
     * with the database have the same connection resource as CakePHP objects.
     *
     * @param array $params
     * @return \Migrations\Test\CommandTester
     */
    protected function getCommandTester($params)
    {
        if (!$this->connection->getDriver()->isConnected()) {
            $this->connection->getDriver()->connect();
        }

        $input = new ArrayInput($params, $this->command->getDefinition());
        $this->command->setInput($input);
        $manager = new CakeManager($this->command->getConfig(), $input, $this->streamOutput);
        $adapter = $manager->getEnvironment('default')->getAdapter();
        while ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }
        $adapter->setConnection($this->getDriverConnection($this->connection->getDriver()));
        $this->command->setManager($manager);
        $commandTester = new CommandTester($this->command);

        return $commandTester;
    }
}
