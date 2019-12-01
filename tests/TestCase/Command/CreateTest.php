<?php
namespace Migrations\Test\Command;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Migrations\CakeManager;
use Migrations\MigrationsDispatcher;
use Phinx\Db\Adapter\WrapperInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class CreateTest extends TestCase
{
    use StringCompareTrait;

    /**
     * Instance of a Symfony Command object
     *
     * @var \Symfony\Component\Console\Command\Command
     */
    protected $command;

    /**
     * Instance of a Phinx Config object
     *
     * @var \Phinx\Config\Config
     */
    protected $config = [];

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $Connection;

    /**
     * Instance of a StreamOutput object.
     * It will store the output from the CommandTester
     *
     * @var \Symfony\Component\Console\Output\StreamOutput
     */
    protected $streamOutput;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->Connection = ConnectionManager::get('test');
        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('create');
        $this->streamOutput = new StreamOutput(fopen('php://memory', 'w', false));
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Create' . DS;
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->Connection, $this->command, $this->streamOutput);
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
            'name' => 'TestCreate',
        ];
        $commandTester = $this->getCommandTester($params);

        $commandTester->execute([
            'command' => $this->command->getName(),
            'name' => 'TestCreate',
            '--connection' => 'test',
        ]);

        $files = glob(ROOT . 'config' . DS . 'Create' . DS . '*_TestCreate*.php');
        $this->assertNotEmpty($files);

        $file = current($files);
        $this->assertSameAsFile('TestCreate.php', file_get_contents($file));
    }

    /**
     * Gets a pre-configured of a CommandTester object that is initialized for each
     * test methods. This is needed in order to define the same PDO connection resource
     * between every objects needed during the tests.
     * This is mandatory for the SQLite database vendor, so phinx objects interacting
     * with the database have the same connection resource as CakePHP objects.
     *
     * @return \Symfony\Component\Console\Tester\CommandTester
     */
    protected function getCommandTester($params)
    {
        if (!$this->Connection->getDriver()->isConnected()) {
            $this->Connection->getDriver()->connect();
        }

        $input = new ArrayInput($params, $this->command->getDefinition());
        $this->command->setInput($input);
        $manager = new CakeManager($this->command->getConfig(), $input, $this->streamOutput);
        $adapter = $manager->getEnvironment('default')->getAdapter();
        while ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }
        $adapter->setConnection($this->Connection->getDriver()->getConnection());
        $this->command->setManager($manager);
        $commandTester = new \Migrations\Test\CommandTester($this->command);

        return $commandTester;
    }
}
