<?php
namespace Migrations\Test\Command;

use Cake\Cache\Cache;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\MigrationsDispatcher;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class CacheBuildTest extends TestCase
{

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
    protected $connection;

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
        Cache::enable();
        $this->connection = ConnectionManager::get('test');
        $this->connection->cacheMetadata(true);
        $this->connection->execute("CREATE TABLE blog (id int NOT NULL, title varchar(200) NOT NULL)");
        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('orm-cache-build');
        $this->streamOutput = new StreamOutput(fopen('php://memory', 'w', false));
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Cache' . DS;
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        $this->connection->execute('DROP TABLE IF EXISTS blog');
        unset($this->connection, $this->command, $this->streamOutput);
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
        ];
        $commandTester = $this->getCommandTester($params);

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
        ]);

        $file = Cache::read('test_blog', '_cake_model_');
        $result = serialize($file);

        $expected = file_get_contents($this->_compareBasePath . '/myapp_cake_model_default_blog');

        $this->assertSame($expected, $result);
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
        if (!$this->connection->driver()->isConnected()) {
            $this->connection->driver()->connect();
        }

        $input = new ArrayInput($params, $this->command->getDefinition());
        $this->command->setInput($input);
        $commandTester = new CommandTester($this->command);

        return $commandTester;
    }
}
