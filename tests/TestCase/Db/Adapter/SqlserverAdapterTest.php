<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use BadMethodCallException;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;
use Migrations\Db\Adapter\SqlserverAdapter;
use Migrations\Db\Literal;
use Migrations\Db\Table;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SqlserverAdapterTest extends TestCase
{
    /**
     * @var \Migrations\Db\Adapter\SqlServerAdapter
     */
    private $adapter;

    private array $config;
    private StubConsoleOutput $out;
    private ConsoleIo $io;

    protected function setUp(): void
    {
        $config = ConnectionManager::getConfig('test');
        if ($config['scheme'] !== 'sqlserver') {
            $this->markTestSkipped('Sqlserver tests disabled.');
        }
        // Emulate the results of Util::parseDsn()
        $this->config = [
            'adapter' => 'sqlserver',
            'connection' => ConnectionManager::get('test'),
            'database' => $config['database'],
        ];

        $this->adapter = new SqlserverAdapter($this->config, $this->getConsoleIo());

        // ensure the database is empty for each test
        $this->adapter->dropDatabase($this->config['database']);
        $this->adapter->createDatabase($this->config['database']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    protected function tearDown(): void
    {
        if (!empty($this->adapter)) {
            $this->adapter->disconnect();
        }
        unset($this->adapter, $this->out, $this->io);
    }

    protected function getConsoleIo(): ConsoleIo
    {
        $out = new StubConsoleOutput();
        $in = new StubConsoleInput([]);
        $io = new ConsoleIo($out, $out, $in);

        $this->out = $out;
        $this->io = $io;

        return $this->io;
    }

    public function testConnection()
    {
        $this->assertInstanceOf(Connection::class, $this->adapter->getConnection());
    }

    public function testCreatingTheSchemaTableOnConnect()
    {
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
        $this->assertFalse($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->disconnect();
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
    }

    public function testSchemaTableIsCreatedWithPrimaryKey()
    {
        $this->adapter->connect();
        new Table($this->adapter->getSchemaTableName(), [], $this->adapter);
        $this->assertTrue($this->adapter->hasIndex($this->adapter->getSchemaTableName(), ['version']));
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('[dbo].[test_table]', $this->adapter->quoteTableName('test_table'));
        $this->assertEquals('[schema].[table]', $this->adapter->quoteTableName('schema.table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('[test_column]', $this->adapter->quoteColumnName('test_column'));
    }

    public function testCreateTable()
    {
        $table = new Table('ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableWithSchema()
    {
        $this->adapter->createSchema('nschema');

        $table = new Table('nschema.ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('nschema.ntable'));
        $this->assertTrue($this->adapter->hasColumn('nschema.ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('nschema.ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('nschema.ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('nschema.ntable', 'address'));

        $this->adapter->dropTable('nschema.ntable');
        $this->adapter->dropSchema('nschema');
    }

    public function testCreateTableCustomIdColumn()
    {
        $table = new Table('ntable', ['id' => 'custom_id'], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableIdentityColumn()
    {
        $table = new Table('ntable', ['id' => false, 'primary_key' => 'id'], $this->adapter);
        $table->addColumn('id', 'integer', ['identity' => true, 'seed' => 1, 'increment' => 10 ])
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));

        $rows = $this->adapter->fetchAll("SELECT CAST(seed_value AS INT) seed_value, CAST(increment_value AS INT) increment_value
FROM sys.columns c JOIN sys.tables t ON c.object_id=t.object_id
JOIN sys.identity_columns ic ON c.object_id=ic.object_id AND c.column_id=ic.column_id
WHERE t.name='ntable'");
        $identity = $rows[0];
        $this->assertEquals($identity['seed_value'], '1');
        $this->assertEquals($identity['increment_value'], '10');
    }

    public function testCreateTableWithNoPrimaryKey()
    {
        $options = [
            'id' => false,
        ];
        $table = new Table('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }

    public function testCreateTableWithConflictingPrimaryKeys()
    {
        $options = [
            'primary_key' => 'user_id',
        ];
        $table = new Table('atable', $options, $this->adapter);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot enable an auto incrementing ID field and a primary key');
        $table->addColumn('user_id', 'integer')->save();
    }

    public function testCreateTableWithPrimaryKeySetToImplicitId()
    {
        $options = [
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    public function testCreateTableWithPrimaryKeyArraySetToImplicitId()
    {
        $options = [
            'primary_key' => ['id'],
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    public function testCreateTableWithMultiplePrimaryKeyArraySetToImplicitId()
    {
        $options = [
            'primary_key' => ['id', 'user_id'],
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot enable an auto incrementing ID field and a primary key');
        $table->addColumn('user_id', 'integer')->save();
    }

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id'],
        ];
        $table = new Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('tag_id', 'integer', ['null' => false])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['tag_id', 'USER_ID']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    public function testCreateTableWithPrimaryKeyAsUuid()
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'uuid', ['null' => false])->save();
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    public function testCreateTableWithPrimaryKeyAsBinaryUuid()
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'binaryuuid', ['null' => false])->save();
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('name', 'string')
              ->addIndex('email')
              ->addIndex('name')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['name']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_name']));
    }

    public function testCreateTableWithUniqueIndexes()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['unique' => true])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndexes()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertTrue($this->adapter->hasIndexByName('table1', 'myemailindex'));
    }

    public function testAddPrimaryKey()
    {
        $table = new Table('table1', ['id' => false], $this->adapter);
        $table
            ->addColumn('column1', 'integer', ['null' => false])
            ->save();

        $table
            ->changePrimaryKey('column1')
            ->save();

        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testChangePrimaryKey()
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer', ['null' => false])
            ->addColumn('column2', 'integer', ['null' => false])
            ->addColumn('column3', 'integer', ['null' => false])
            ->save();

        $table
            ->changePrimaryKey(['column2', 'column3'])
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column2', 'column3']));
    }

    public function testDropPrimaryKey()
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer', ['null' => false])
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testHasPrimaryKeyMultipleColumns()
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => ['column1', 'column2', 'column3']], $this->adapter);
        $table
            ->addColumn('column1', 'integer', ['null' => false])
            ->addColumn('column2', 'integer', ['null' => false])
            ->addColumn('column3', 'integer', ['null' => false])
            ->save();

        $this->assertFalse($table->hasPrimaryKey(['column1', 'column2']));
        $this->assertTrue($table->hasPrimaryKey(['column1', 'column2', 'column3']));
        $this->assertFalse($table->hasPrimaryKey(['column1', 'column2', 'column3', 'column4']));
    }

    public function testHasPrimaryKeyCaseSensitivity()
    {
        $table = new Table('table', ['id' => false, 'primary_key' => ['column1']], $this->adapter);
        $table
            ->addColumn('column1', 'integer', ['null' => false])
            ->save();

        $this->assertTrue($table->hasPrimaryKey('column1'));
        $this->assertFalse($table->hasPrimaryKey('cOlUmN1'));
    }

    public function testChangeCommentFails()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $this->expectException(BadMethodCallException::class);

        $table
            ->changeComment('comment1')
            ->save();
    }

    public function testRenameTable()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));
        $this->adapter->renameTable('table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }

    public function testAddColumn()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
              ->save();
        $this->assertTrue($table->hasColumn('email'));
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
              ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'default_zero') {
                $this->assertEquals('test', $column->getDefault());
            }
        }
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
              ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'default_zero') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('0', $column->getDefault());
            }
        }
    }

    public function testAddColumnWithDefaultNull()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_null', 'string', ['null' => true, 'default' => null])
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'default_null') {
                $this->assertNull($column->getDefault());
            }
        }
    }

    public function testAddColumnWithNotNullableNoDefault()
    {
        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('col', 'string', ['null' => false])
            ->create();

        $columns = $this->adapter->getColumns('table1');
        $this->assertCount(2, $columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('col', $columns);
        $this->assertFalse($columns['col']->isNull());
        $this->assertNull($columns['col']->getDefault());
    }

    public function testAddColumnWithDefaultBool()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table
            ->addColumn('default_false', 'integer', ['default' => false])
            ->addColumn('default_true', 'integer', ['default' => true])
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'default_false') {
                $this->assertSame(0, $column->getDefault());
            }
            if ($column->getName() === 'default_true') {
                $this->assertSame(1, $column->getDefault());
            }
        }
    }

    public function testRenameColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('t', 'column2'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenamingANonExistentColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        try {
            $this->adapter->renameColumn('t', 'column2', 'column1');
            $this->fail('Expected the adapter to throw an exception');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertEquals('The specified column does not exist: column2', $e->getMessage());
        }
    }

    public function testChangeColumnType()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new Column();
        $newColumn1->setName('column1')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertEquals('string', $column->getType());
            }
        }
    }

    public function testChangeColumnNameAndNull()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['null' => false])
            ->save();
        $newColumn2 = new Column();
        $newColumn2->setName('column2')
            ->setType('string')
            ->setNull(true);
        $table->changeColumn('column1', $newColumn2)->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column2') {
                $this->assertTrue($column->isNull());
            }
        }
    }

    public function testChangeColumnDefaults()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $columns = $this->adapter->getColumns('t');
        $this->assertSame('test', $columns['column1']->getDefault());

        $newColumn1 = new Column();
        $newColumn1
            ->setName('column1')
            ->setType('string')
            ->setDefault('another test');
        $table->changeColumn('column1', $newColumn1)->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $columns = $this->adapter->getColumns('t');
        $this->assertSame('another test', $columns['column1']->getDefault());
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['null' => true, 'default' => 'test'])
            ->save();
        $newColumn1 = new Column();
        $newColumn1
            ->setName('column1')
            ->setType('string')
            ->setDefault(null);
        $table->changeColumn('column1', $newColumn1)->save();
        $columns = $this->adapter->getColumns('t');
        $this->assertNull($columns['column1']->getDefault());
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new Column();
        $newColumn1
            ->setName('column1')
            ->setType('string')
            ->setDefault(0);
        $table->changeColumn('column1', $newColumn1)->save();
        $columns = $this->adapter->getColumns('t');
        $this->assertSame(0, $columns['column1']->getDefault());
    }

    public function testDropColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->dropColumn('t', 'column1');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
    }

    public static function columnsProvider()
    {
        return [
            ['column1', 'string', ['null' => true, 'default' => null]],
            ['column2', 'integer', ['default' => 0]],
            ['column3', 'biginteger', ['default' => 5]],
            ['column4', 'text', ['default' => 'text']],
            ['column5', 'float', []],
            ['column6', 'decimal', []],
            ['column7', 'time', []],
            ['column8', 'date', []],
            ['column9', 'boolean', []],
            ['column10', 'datetime', []],
            ['column11', 'binary', []],
            ['column12', 'string', ['limit' => 10]],
            ['column13', 'tinyinteger', ['default' => 5]],
            ['column14', 'smallinteger', ['default' => 5]],
            ['decimal_precision_scale', 'decimal', ['precision' => 10, 'scale' => 2]],
            ['decimal_limit', 'decimal', ['limit' => 10]],
            ['decimal_precision', 'decimal', ['precision' => 10]],
        ];
    }

    #[DataProvider('columnsProvider')]
    public function testGetColumns($colName, $type, $options)
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn($colName, $type, $options)
            ->save();

        $columns = $this->adapter->getColumns('t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[$colName]->getName());
        $this->assertEquals($type, $columns[$colName]->getType());
    }

    public function testAddIndex()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
    }

    public function testAddIndexWithSort()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->save();
        $this->assertFalse($table->hasIndexByName('table1_email_username'));
        $table->addIndex(['email', 'username'], ['name' => 'table1_email_username', 'order' => ['email' => 'DESC', 'username' => 'ASC']])
              ->save();
        $this->assertTrue($table->hasIndexByName('table1_email_username'));
        $rows = $this->adapter->fetchAll("SELECT case when ic.is_descending_key = 1 then 'DESC' else 'ASC' end AS sort_order
                        FROM   sys.indexes AS i
                        INNER JOIN sys.index_columns AS ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                        INNER JOIN sys.tables AS t ON i.object_id=t.object_id
                        INNER JOIN sys.columns AS c on ic.column_id=c.column_id and ic.object_id=c.object_id
                        WHERE   t.name = 'table1' AND i.name = 'table1_email_username' AND c.name = 'email'");
        $emailOrder = $rows[0];
        $this->assertEquals($emailOrder['sort_order'], 'DESC');
        $rows = $this->adapter->fetchAll("SELECT case when ic.is_descending_key = 1 then 'DESC' else 'ASC' end AS sort_order
                        FROM   sys.indexes AS i
                        INNER JOIN sys.index_columns AS ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                        INNER JOIN sys.tables AS t ON i.object_id=t.object_id
                        INNER JOIN sys.columns AS c on ic.column_id=c.column_id and ic.object_id=c.object_id
                        WHERE   t.name = 'table1' AND i.name = 'table1_email_username' AND c.name = 'username'");
        $emailOrder = $rows[0];
        $this->assertEquals($emailOrder['sort_order'], 'ASC');
    }

    public function testAddIndexWithIncludeColumns()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('firstname', 'string')
              ->addColumn('lastname', 'string')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex(['email'], ['include' => ['firstname', 'lastname']])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $rows = $this->adapter->fetchAll("SELECT ic.is_included_column AS included
                        FROM   sys.indexes AS i
                        INNER JOIN sys.index_columns AS ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                        INNER JOIN sys.tables AS t ON i.object_id=t.object_id
                        INNER JOIN sys.columns AS c on ic.column_id=c.column_id and ic.object_id=c.object_id
                        WHERE   t.name = 'table1' AND c.name = 'email'");
        $emailOrder = $rows[0];
        $this->assertEquals($emailOrder['included'], 0);
        $rows = $this->adapter->fetchAll("SELECT ic.is_included_column AS included
                        FROM   sys.indexes AS i
                        INNER JOIN sys.index_columns AS ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                        INNER JOIN sys.tables AS t ON i.object_id=t.object_id
                        INNER JOIN sys.columns AS c on ic.column_id=c.column_id and ic.object_id=c.object_id
                        WHERE   t.name = 'table1' AND c.name = 'firstname'");
        $emailOrder = $rows[0];
        $this->assertEquals($emailOrder['included'], 1);
        $rows = $this->adapter->fetchAll("SELECT ic.is_included_column AS included
                        FROM   sys.indexes AS i
                        INNER JOIN sys.index_columns AS ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                        INNER JOIN sys.tables AS t ON i.object_id=t.object_id
                        INNER JOIN sys.columns AS c on ic.column_id=c.column_id and ic.object_id=c.object_id
                        WHERE   t.name = 'table1' AND c.name = 'lastname'");
        $emailOrder = $rows[0];
        $this->assertEquals($emailOrder['included'], 1);
    }

    public function testGetIndexes()
    {
        // single column index
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->addIndex('email')
              ->addIndex(['email', 'username'], ['unique' => true, 'name' => 'email_username'])
              ->save();

        $indexes = $this->adapter->getIndexes('table1');
        $this->assertArrayHasKey('PK_table1', $indexes);
        $this->assertArrayHasKey('table1_email', $indexes);
        $this->assertArrayHasKey('email_username', $indexes);

        $this->assertEquals(['id'], $indexes['PK_table1']['columns']);
        $this->assertEquals(['email'], $indexes['table1_email']['columns']);
        $this->assertEquals(['email', 'username'], $indexes['email_username']['columns']);
    }

    public function testDropIndex()
    {
        // single column index
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table2->getName(), ['fname', 'lname']);
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // index with name specified, but dropping it by column name
        $table3 = new Table('table3', [], $this->adapter);
        $table3->addColumn('email', 'string')
               ->addIndex('email', ['name' => 'someindexname'])
               ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $this->adapter->dropIndex($table3->getName(), 'email');
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new Table('table4', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table4->getName(), ['fname', 'lname']);
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndexByName($table->getName(), 'myemailindex');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(
                   ['fname', 'lname'],
                   ['name' => 'twocolumnuniqueindex', 'unique' => true]
               )
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnuniqueindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));
    }

    public function testAddForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new ForeignKey();
        $fk->setReferencedTable($refTable->getTable())
           ->setColumns(['ref_table_id'])
           ->setReferencedColumns(['id'])
           ->setConstraint('fk1');

        $this->adapter->addForeignKey($table->getTable(), $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'fk1'));
    }

    public function testDropForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);

        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyWithMultipleColumns()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string')
            ->addColumn('field2', 'string')
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->addIndex(['field1', 'id'], ['unique' => true])
            ->addIndex(['id', 'field1', 'field2'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addColumn('ref_table_field1', 'string')
            ->addColumn('ref_table_field2', 'string')
            ->addForeignKey(
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1']
            )
            ->addForeignKey(
                ['ref_table_field1', 'ref_table_id'],
                'ref_table',
                ['field1', 'id']
            )
            ->addForeignKey(
                ['ref_table_id', 'ref_table_field1', 'ref_table_field2'],
                'ref_table',
                ['id', 'field1', 'field2']
            )
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->assertTrue(
            $this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1', 'ref_table_field2']),
            'dropForeignKey() should only affect foreign keys that comprise of exactly the given columns'
        );
        $this->assertTrue(
            $this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']),
            'dropForeignKey() should only affect foreign keys that comprise of columns in exactly the given order'
        );

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']));
        $this->adapter->dropForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']));
    }

    public function testDropForeignKeyWithIdenticalMultipleColumns()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string')
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string')
            ->addForeignKeyWithName(
                'ref_table_fk_1',
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1'],
            )
            ->addForeignKeyWithName(
                'ref_table_fk_2',
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1']
            )
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_1'));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_2'));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']);

        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_1'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_2'));
    }

    public static function nonExistentForeignKeyColumnsProvider(): array
    {
        return [
            [['ref_table_id']],
            [['ref_table_field1']],
            [['ref_table_field1', 'ref_table_id']],
            [['non_existent_column']],
        ];
    }

    /**
     * @param array $columns
     */
    #[DataProvider('nonExistentForeignKeyColumnsProvider')]
    public function testDropForeignKeyByNonExistentKeyColumns(array $columns)
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string')
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addColumn('ref_table_field1', 'string')
            ->addForeignKey(
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1']
            )
            ->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'No foreign key on column(s) `%s` exists',
            implode(', ', $columns)
        ));

        $this->adapter->dropForeignKey($table->getName(), $columns);
    }

    public function testDropForeignKeyCaseSensitivity()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('REF_TABLE_ID', 'integer')
            ->addForeignKey(['REF_TABLE_ID'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['REF_TABLE_ID']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'No foreign key on column(s) `%s` exists',
            implode(', ', ['ref_table_id'])
        ));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);
    }

    public function testDropForeignKeyByName()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), [], 'my_constraint');
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    #[DataProvider('provideForeignKeysToCheck')]
    public function testHasForeignKey($tableDef, $key, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->execute('CREATE TABLE other(a int, b int, c int, unique(a), unique(b), unique(a,b), unique(a,b,c));');
        $conn->execute($tableDef);
        $this->assertSame($exp, $this->adapter->hasForeignKey('t', $key));
    }

    public static function provideForeignKeysToCheck()
    {
        return [
            ['create table t(a int)', 'a', false],
            ['create table t(a int)', [], false],
            ['create table t(a int primary key)', 'a', false],
            ['create table t(a int, foreign key (a) references other(a))', 'a', true],
            ['create table t(a int, foreign key (a) references other(b))', 'a', true],
            ['create table t(a int, foreign key (a) references other(b))', ['a'], true],
            ['create table t(a int, foreign key (a) references other(b))', ['a', 'a'], false],
            ['create table t(a int, foreign key(a) references other(a))', 'a', true],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', 'a', false],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', ['a', 'b'], true],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', ['b', 'a'], false],
            ['create table t(a int, [B] int, foreign key(a,[B]) references other(a,b))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', ['a', 'B'], false],
            ['create table t(a int, b int, c int, foreign key(a,b,c) references other(a,b,c))', ['a', 'b'], false],
            ['create table t(a int, foreign key(a) references other(a))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t([0] int, foreign key([0]) references other(a))', '0', true],
            ['create table t([0] int, foreign key([0]) references other(a))', '0e0', false],
            ['create table t([0e0] int, foreign key([0e0]) references other(a))', '0', false],
        ];
    }

    public function testHasNamedForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint2'));
    }

    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase($this->config['database']));
    }

    public function testDropDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->createDatabase('phinx_temp_database');
        $this->assertTrue($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->dropDatabase('phinx_temp_database');
    }

    public function testCreateSchema()
    {
        $this->adapter->createSchema('foo');
        $this->assertTrue($this->adapter->hasSchema('foo'));
    }

    public function testDropSchema()
    {
        $this->adapter->createSchema('foo');
        $this->assertTrue($this->adapter->hasSchema('foo'));
        $this->adapter->dropSchema('foo');
        $this->assertFalse($this->adapter->hasSchema('foo'));
    }

    public function testDropAllSchemas()
    {
        $this->adapter->createSchema('foo');
        $this->adapter->createSchema('bar');

        $this->assertTrue($this->adapter->hasSchema('foo'));
        $this->assertTrue($this->adapter->hasSchema('bar'));
        $this->adapter->dropAllSchemas();
        $this->assertFalse($this->adapter->hasSchema('foo'));
        $this->assertFalse($this->adapter->hasSchema('bar'));
    }

    public function testQuoteSchemaName()
    {
        $this->assertEquals('[schema]', $this->adapter->quoteSchemaName('schema'));
        $this->assertEquals('[schema.schema]', $this->adapter->quoteSchemaName('schema.schema'));
    }

    public function testInvalidSqlType()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column type "idontexist" is not supported by SqlServer.');

        $this->adapter->getSqlType('idontexist');
    }

    public function testGetPhinxType()
    {
        $this->assertEquals('integer', $this->adapter->getPhinxType('int'));
        $this->assertEquals('integer', $this->adapter->getPhinxType('integer'));

        $this->assertEquals('tinyinteger', $this->adapter->getPhinxType('tinyint'));
        $this->assertEquals('smallinteger', $this->adapter->getPhinxType('smallint'));
        $this->assertEquals('biginteger', $this->adapter->getPhinxType('bigint'));

        $this->assertEquals('decimal', $this->adapter->getPhinxType('decimal'));
        $this->assertEquals('decimal', $this->adapter->getPhinxType('numeric'));

        $this->assertEquals('float', $this->adapter->getPhinxType('real'));

        $this->assertEquals('boolean', $this->adapter->getPhinxType('bit'));

        $this->assertEquals('string', $this->adapter->getPhinxType('varchar'));
        $this->assertEquals('string', $this->adapter->getPhinxType('nvarchar'));
        $this->assertEquals('char', $this->adapter->getPhinxType('char'));
        $this->assertEquals('char', $this->adapter->getPhinxType('nchar'));

        $this->assertEquals('text', $this->adapter->getPhinxType('text'));

        $this->assertEquals('datetime', $this->adapter->getPhinxType('timestamp'));

        $this->assertEquals('date', $this->adapter->getPhinxType('date'));

        $this->assertEquals('datetime', $this->adapter->getPhinxType('datetime'));
    }

    public function testAddColumnComment()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => $comment = 'Comments from column "field1"'])
              ->save();

        $resultComment = $this->adapter->getColumnComment('table1', 'field1');

        $this->assertEquals($comment, $resultComment, 'Dont set column comment correctly');
    }

    #[Depends('testAddColumnComment')]
    public function testChangeColumnComment()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
              ->save();

        $table->changeColumn('field1', 'string', ['comment' => $comment = 'New Comments from column "field1"'])
              ->save();

        $resultComment = $this->adapter->getColumnComment('table1', 'field1');

        $this->assertEquals($comment, $resultComment, 'Dont change column comment correctly');
    }

    #[Depends('testAddColumnComment')]
    public function testRemoveColumnComment()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
              ->save();

        $table->changeColumn('field1', 'string', ['comment' => 'null'])
              ->save();

        $resultComment = $this->adapter->getColumnComment('table1', 'field1');

        $this->assertEmpty($resultComment, "Didn't remove column comment correctly: " . json_encode($resultComment));
    }

    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testForignKeysArePropertlyEscaped()
    {
        $userId = 'user';
        $sessionId = 'session';

        $local = new Table('users', ['id' => $userId], $this->adapter);
        $local->create();

        $foreign = new Table('sessions', ['id' => $sessionId], $this->adapter);
        $foreign->addColumn('user', 'integer')
                ->addForeignKey('user', 'users', $userId)
                ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));
    }

    public function testBulkInsertData()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->save();
        $table->insert([
                  [
                      'column1' => 'value1',
                      'column2' => 1,
                  ],
                  [
                      'column1' => 'value2',
                      'column2' => 2,
                  ],
              ])
              ->insert(
                  [
                      'column1' => 'value3',
                      'column2' => 3,
                  ]
              );
        $this->adapter->bulkinsert($table->getTable(), $table->getData());
        $table->reset();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
    }

    public function testBulkInsertLiteral()
    {
        $data = [
            [
                'column1' => 'value1',
                'column2' => Literal::from('CURRENT_TIMESTAMP'),
            ],
            [
                'column1' => 'value2',
                'column2' => '2024-01-01 00:00:00',
            ],
            [
                'column1' => 'value3',
                'column2' => '2025-01-01 00:00:00',
            ],
        ];
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'datetime')
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertMatchesRegularExpression('/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/', $rows[0]['column2']);
        $this->assertEquals('2024-01-01 00:00:00.000', $rows[1]['column2']);
        $this->assertEquals('2025-01-01 00:00:00.000', $rows[2]['column2']);
    }

    public function testInsertData()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->insert([
                  [
                      'column1' => 'value1',
                      'column2' => 1,
                  ],
                  [
                      'column1' => 'value2',
                      'column2' => 2,
                  ],
              ])
              ->insert(
                  [
                      'column1' => 'value3',
                      'column2' => 3,
                  ]
              )
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
    }

    public function testInsertLiteral()
    {
        $data = [
            [
                'column1' => 'value1',
                'column3' => Literal::from('CURRENT_TIMESTAMP'),
            ],
            [
                'column1' => 'value2',
                'column3' => '2024-01-01 00:00:00',
            ],
            [
                'column1' => 'value3',
                'column2' => 'foo',
                'column3' => '2025-01-01 00:00:00',
            ],
        ];
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'string', ['default' => 'test'])
            ->addColumn('column3', 'datetime')
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals('test', $rows[0]['column2']);
        $this->assertEquals('test', $rows[1]['column2']);
        $this->assertEquals('foo', $rows[2]['column2']);
        $this->assertMatchesRegularExpression('/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/', $rows[0]['column3']);
        $this->assertEquals('2024-01-01 00:00:00.000', $rows[1]['column3']);
        $this->assertEquals('2025-01-01 00:00:00.000', $rows[2]['column3']);
    }

    public function testTruncateTable()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->insert([
                  [
                      'column1' => 'value1',
                      'column2' => 1,
                  ],
                  [
                      'column1' => 'value2',
                      'column2' => 2,
                  ],
              ])
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertCount(2, $rows);
        $table->truncate();
        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertCount(0, $rows);
    }

    public function testDumpCreateTableAndThenInsert()
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $table = new Table('table1', ['id' => false, 'primary_key' => ['column1']], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->save();

        $expectedOutput = 'C';

        $table = new Table('table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1,
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE [dbo].[table1] ([column1] NVARCHAR (255)   NOT NULL , [column2] INT   NULL  DEFAULT NULL, CONSTRAINT PK_table1 PRIMARY KEY ([column1]));
INSERT INTO [dbo].[table1] ([column1], [column2]) VALUES ('id1', 1);
OUTPUT;
        $output = join("\n", $this->out->messages());
        $actualOutput = str_replace("\r\n", "\n", $output);
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create and then insert table queries to the output');
    }

    /**
     * Tests interaction with the query builder
     */
    public function testQueryBuilder()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $builder = $this->adapter->getInsertBuilder();
        $stm = $builder
            ->insert(['string_col', 'int_col'])
            ->into('table1')
            ->values(['string_col' => 'value1', 'int_col' => 1])
            ->values(['string_col' => 'value2', 'int_col' => 2])
            ->execute();

        $stm->closeCursor();

        $builder = $this->adapter->getSelectBuilder();
        $stm = $builder
            ->select('*')
            ->from('table1')
            ->where(['int_col >=' => 2])
            ->execute();

        $this->assertEquals(1, $stm->rowCount());
        $this->assertEquals(
            ['id' => 2, 'string_col' => 'value2', 'int_col' => '2'],
            $stm->fetch('assoc')
        );

        $stm->closeCursor();

        $builder = $this->adapter->getDeleteBuilder();
        $stm = $builder
            ->delete('table1')
            ->where(['int_col <' => 2])
            ->execute();

        $stm->closeCursor();
    }

    public function testQueryWithParams()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $this->adapter->insert($table->getTable(), [
            'string_col' => 'test data',
            'int_col' => 10,
        ]);

        $this->adapter->insert($table->getTable(), [
            'string_col' => null,
        ]);

        $this->adapter->insert($table->getTable(), [
            'int_col' => 23,
        ]);

        $countQuery = $this->adapter->query('SELECT COUNT(*) AS c FROM table1 WHERE int_col > ?', [5]);
        $res = $countQuery->fetchAll('assoc');
        $this->assertEquals(2, $res[0]['c']);

        $this->adapter->execute('UPDATE table1 SET int_col = ? WHERE int_col IS NULL', [12]);

        $countQuery->execute([1]);
        $res = $countQuery->fetchAll('assoc');
        $this->assertEquals(3, $res[0]['c']);
    }

    public function testLiteralSupport()
    {
        $createQuery = <<<'INPUT'
CREATE TABLE test (smallmoney_col smallmoney)
INPUT;
        $this->adapter->execute($createQuery);
        $table = new Table('test', [], $this->adapter);
        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertEquals(Literal::from('smallmoney'), array_pop($columns)->getType());
    }
}
