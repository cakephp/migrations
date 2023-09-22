<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command\Phinx;

use Cake\Cache\Cache;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\MigrationsDispatcher;
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
     * @var string
     */
    protected $_compareBasePath;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        Cache::enable();
        $this->connection = ConnectionManager::get('test');
        $this->connection->cacheMetadata(true);
        $this->connection->execute('DROP TABLE IF EXISTS blog');
        $this->connection->execute('CREATE TABLE blog (id int NOT NULL, title varchar(200) NOT NULL)');
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
    public function tearDown(): void
    {
        parent::tearDown();

        Cache::disable();
        $this->connection->cacheMetadata(false);
        $this->connection->execute('DROP TABLE IF EXISTS blog');
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

        $this->assertNotFalse(Cache::read('test_blog', '_cake_model_'));
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

        //$input = new ArrayInput($params, $this->command->getDefinition());
        $commandTester = new CommandTester($this->command);

        return $commandTester;
    }
}
