<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use BadMethodCallException;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Database\Query;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;
use Migrations\Db\Adapter\PhinxAdapter;
use Migrations\Db\Adapter\SqliteAdapter;
use Migrations\Db\Literal;
use Migrations\Db\Table\ForeignKey;
use PDO;
use PDOException;
use Phinx\Db\Table as PhinxTable;
use Phinx\Db\Table\Column as PhinxColumn;
use Phinx\Util\Literal as PhinxLiteral;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use UnexpectedValueException;

class PhinxAdapterTest extends TestCase
{
    /**
     * @var \Migrations\Db\Adapter\PhinxAdapter
     */
    private $adapter;

    /**
     * @var array
     */
    private $config;
    private StubConsoleOutput $out;
    private ConsoleIo $io;

    protected function setUp(): void
    {
        /** @var array<string, mixed> $config */
        $config = ConnectionManager::getConfig('test');
        if ($config['scheme'] !== 'sqlite') {
            $this->markTestSkipped('phinx adapter tests require sqlite');
        }
        // Emulate the results of Util::parseDsn()
        $this->config = [
            'adapter' => 'sqlite',
            'connection' => ConnectionManager::get('test'),
            'database' => $config['database'],
            'suffix' => '',
        ];
        $this->adapter = new PhinxAdapter(
            new SqliteAdapter(
                $this->config,
                $this->getConsoleIo()
            )
        );

        if ($config['database'] !== ':memory:') {
            // ensure the database is empty for each test
            $this->adapter->dropDatabase($config['database']);
            $this->adapter->createDatabase($config['database']);
        }

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    protected function tearDown(): void
    {
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

    public function testBeginTransaction()
    {
        $this->adapter->beginTransaction();

        $this->assertTrue(
            $this->adapter->getConnection()->inTransaction(),
            'Underlying PDO instance did not detect new transaction'
        );
        $this->adapter->rollbackTransaction();
    }

    public function testRollbackTransaction()
    {
        $this->adapter->beginTransaction();
        $this->adapter->rollbackTransaction();

        $this->assertFalse(
            $this->adapter->getConnection()->inTransaction(),
            'Underlying PDO instance did not detect rolled back transaction'
        );
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('`test_table`', $this->adapter->quoteTableName('test_table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('`test_column`', $this->adapter->quoteColumnName('test_column'));
    }

    public function testCreateTable()
    {
        $table = new PhinxTable('ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableCustomIdColumn()
    {
        $table = new PhinxTable('ntable', ['id' => 'custom_id'], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        //ensure the primary key is not nullable
        /** @var \Phinx\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertInstanceOf(PhinxColumn::class, $idColumn);
        $this->assertTrue($idColumn->getIdentity());
        $this->assertFalse($idColumn->isNull());
    }

    public function testCreateTableIdentityIdColumn()
    {
        $table = new PhinxTable('ntable', ['id' => false, 'primary_key' => ['custom_id']], $this->adapter);
        $table->addColumn('custom_id', 'integer', ['identity' => true])
            ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));

        /** @var \Phinx\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertInstanceOf(PhinxColumn::class, $idColumn);
        $this->assertTrue($idColumn->getIdentity());
    }

    public function testCreateTableWithNoPrimaryKey()
    {
        $options = [
            'id' => false,
        ];
        $table = new PhinxTable('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id'],
        ];
        $table = new PhinxTable('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['USER_ID', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'USER_ID']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsUuid()
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new PhinxTable('ztable', $options, $this->adapter);
        $table->addColumn('id', 'uuid')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsBinaryUuid()
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new PhinxTable('ztable', $options, $this->adapter);
        $table->addColumn('id', 'binaryuuid')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
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
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['unique' => true])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndexes()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailindex'])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertTrue($this->adapter->hasIndexByName('table1', 'myemailindex'));
    }

    public function testCreateTableWithForeignKey()
    {
        $refTable = new PhinxTable('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new PhinxTable('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer');
        $table->addForeignKey('ref_table_id', 'ref_table', 'id');
        $table->save();

        $this->assertTrue($this->adapter->hasTable($table->getName()));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testCreateTableWithIndexesAndForeignKey()
    {
        $refTable = new PhinxTable('tbl_master', [], $this->adapter);
        $refTable->create();

        $table = new PhinxTable('tbl_child', [], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->addColumn('master_id', 'integer')
            ->addIndex(['column2'])
            ->addIndex(['column1', 'column2'], ['unique' => true, 'name' => 'uq_tbl_child_column1_column2_ndx'])
            ->addForeignKey(
                'master_id',
                'tbl_master',
                'id',
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_master_id']
            )
            ->create();

        $this->assertTrue($this->adapter->hasIndex('tbl_child', 'column2'));
        $this->assertTrue($this->adapter->hasIndex('tbl_child', ['column1', 'column2']));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['master_id']));

        $row = $this->adapter->fetchRow(
            "SELECT * FROM sqlite_master WHERE `type` = 'table' AND `tbl_name` = 'tbl_child'"
        );
        $this->assertStringContainsString(
            'CONSTRAINT `fk_master_id` FOREIGN KEY (`master_id`) REFERENCES `tbl_master` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION',
            $row['sql']
        );
    }

    public function testCreateTableWithoutAutoIncrementingPrimaryKeyAndWithForeignKey()
    {
        $refTable = (new PhinxTable('tbl_master', ['id' => false, 'primary_key' => 'id'], $this->adapter))
            ->addColumn('id', 'text');
        $refTable->create();

        $table = (new PhinxTable('tbl_child', ['id' => false, 'primary_key' => 'master_id'], $this->adapter))
            ->addColumn('master_id', 'text')
            ->addForeignKey(
                'master_id',
                'tbl_master',
                'id',
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_master_id']
            );
        $table->create();

        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['master_id']));

        $row = $this->adapter->fetchRow(
            "SELECT * FROM sqlite_master WHERE `type` = 'table' AND `tbl_name` = 'tbl_child'"
        );
        $this->assertStringContainsString(
            'CONSTRAINT `fk_master_id` FOREIGN KEY (`master_id`) REFERENCES `tbl_master` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION',
            $row['sql']
        );
    }

    public function testAddPrimaryKey()
    {
        $table = new PhinxTable('table1', ['id' => false], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey('column1')
            ->save();

        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testChangePrimaryKey()
    {
        $table = new PhinxTable('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey('column2')
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column2']));
    }

    public function testChangePrimaryKeyNonInteger()
    {
        $table = new PhinxTable('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'string')
            ->addColumn('column2', 'string')
            ->save();

        $table
            ->changePrimaryKey('column2')
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column2']));
    }

    public function testDropPrimaryKey()
    {
        $table = new PhinxTable('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testAddMultipleColumnPrimaryKeyFails()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $this->expectException(InvalidArgumentException::class);

        $table
            ->changePrimaryKey(['column1', 'column2'])
            ->save();
    }

    public function testChangeCommentFails()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->save();

        $this->expectException(BadMethodCallException::class);

        $table
            ->changeComment('comment1')
            ->save();
    }

    public function testAddColumn()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string', ['null' => true])
            ->save();
        $this->assertTrue($table->hasColumn('email'));

        // In SQLite it is not possible to dictate order of added columns.
        // $table->addColumn('realname', 'string', array('after' => 'id'))
        //       ->save();
        // $this->assertEquals('realname', $rows[1]['Field']);
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("'test'", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertNotNull($rows[1]['dflt_value']);
        $this->assertEquals('0', $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("''", $rows[1]['dflt_value']);
    }

    public function testAddDoubleColumn()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('foo', 'double', ['null' => true])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals('DOUBLE', $rows[1]['type']);
    }

    public function testRenameColumnWithIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));
    }

    public function testRenameColumnWithUniqueIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol', ['unique' => true])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));
    }

    public function testRenameColumnWithCompositeIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addIndex(['indexcol1', 'indexcol2'])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol2']));

        $table->renameColumn('indexcol2', 'newindexcol2')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol2']));
    }

    /**
     * Tests that rewriting the index SQL does not accidentally change
     * the table name in case it matches the column name.
     */
    public function testRenameColumnWithIndexMatchingTheTableName()
    {
        $table = new PhinxTable('indexcol', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));
    }

    /**
     * Tests that rewriting the index SQL does not accidentally change
     * column names that partially match the column to rename.
     */
    public function testRenameColumnWithIndexColumnPartialMatch()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addColumn('indexcolumn', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (indexcolumn, indexcol)');

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'indexcol']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'newindexcol']));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'indexcol']));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'newindexcol']));
    }

    public function testRenameColumnWithIndexColumnRequiringQuoting()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'new index col'));

        $table->renameColumn('indexcol', 'new index col')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'new index col'));
    }

    /**
     * Indices that are using expressions are not being updated.
     */
    public function testRenameColumnWithExpressionIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (`indexcol`, ABS(`indexcol`))');

        $this->assertTrue($this->adapter->hasIndexByName('t', 'custom_idx'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: indexcol');

        $table->renameColumn('indexcol', 'newindexcol')->update();
    }

    public function testChangeColumn()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new PhinxColumn();
        $newColumn1->setName('column1');
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn2 = new PhinxColumn();
        $newColumn2->setName('column2')
            ->setType('string');
        $table->changeColumn('column1', $newColumn2)->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testChangeColumnDefaultValue()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $newColumn1 = new PhinxColumn();
        $newColumn1
            ->setName('column1')
            ->setDefault('test1')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');

        $this->assertEquals("'test1'", $rows[1]['dflt_value']);
    }

    /**
     * @group bug922
     */
    public function testChangeColumnWithForeignKey()
    {
        $refTable = new PhinxTable('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new PhinxTable('another_table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $table->changeColumn('ref_table_id', 'float')->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testChangeColumnWithIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex(
                'indexcol',
                ['unique' => true]
            )
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->changeColumn('indexcol', 'integer', ['null' => false])->update();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testChangeColumnWithTrigger()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('triggercol', 'integer')
            ->addColumn('othercol', 'integer')
            ->create();

        $triggerSQL =
            'CREATE TRIGGER update_t_othercol UPDATE OF triggercol ON t
                BEGIN
                    UPDATE t SET othercol = new.triggercol;
                END';

        $this->adapter->execute($triggerSQL);

        $rows = $this->adapter->fetchAll(
            "SELECT * FROM sqlite_master WHERE `type` = 'trigger' AND tbl_name = 't'"
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('trigger', $rows[0]['type']);
        $this->assertEquals('update_t_othercol', $rows[0]['name']);
        $this->assertEquals($triggerSQL, $rows[0]['sql']);

        $table->changeColumn('triggercol', 'integer', ['null' => false])->update();

        $rows = $this->adapter->fetchAll(
            "SELECT * FROM sqlite_master WHERE `type` = 'trigger' AND tbl_name = 't'"
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('trigger', $rows[0]['type']);
        $this->assertEquals('update_t_othercol', $rows[0]['name']);
        $this->assertEquals($triggerSQL, $rows[0]['sql']);
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new PhinxColumn();
        $newColumn1->setDefault(0)
            ->setName('column1')
            ->setType('integer');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertEquals('0', $rows[1]['dflt_value']);
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $newColumn1 = new PhinxColumn();
        $newColumn1->setDefault(null)
            ->setName('column1')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertNull($rows[1]['dflt_value']);
    }

    public function testChangeColumnWithCommasInCommentsOrDefaultValue()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'one, two or three', 'comment' => 'three, two or one'])
            ->save();
        $newColumn1 = new PhinxColumn();
        $newColumn1->setDefault('another default')
            ->setName('column1')
            ->setComment('another comment')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $cols = $this->adapter->getColumns('t');
        $this->assertEquals('another default', (string)$cols[1]->getDefault());
    }

    public function testDropColumnWithIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testDropColumnWithUniqueIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol', ['unique' => true])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testDropColumnWithCompositeIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addIndex(['indexcol1', 'indexcol2'])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));

        $table->removeColumn('indexcol2')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));
    }

    /**
     * Tests that removing columns does not accidentally drop indices
     * on table names that match the column to remove.
     */
    public function testDropColumnWithIndexMatchingTheTableName()
    {
        $table = new PhinxTable('indexcol', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addColumn('indexcolumn', 'integer')
            ->addIndex('indexcolumn')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));

        $table->removeColumn('indexcol')->update();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));
    }

    /**
     * Tests that removing columns does not accidentally drop indices
     * that contain column names that partially match the column to remove.
     */
    public function testDropColumnWithIndexColumnPartialMatch()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addColumn('indexcolumn', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (indexcolumn)');

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));

        $table->removeColumn('indexcol')->update();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));
    }

    /**
     * Indices with expressions are not being removed.
     */
    public function testDropColumnWithExpressionIndex()
    {
        $table = new PhinxTable('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (ABS(indexcol))');

        $this->assertTrue($this->adapter->hasIndexByName('t', 'custom_idx'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: indexcol');

        $table->removeColumn('indexcol')->update();
    }

    public static function columnsProvider()
    {
        return [
            ['column1', 'string', []],
            ['column2', 'integer', []],
            ['column3', 'biginteger', []],
            ['column4', 'text', []],
            ['column5', 'float', []],
            ['column7', 'datetime', []],
            ['column8', 'time', []],
            ['column9', 'timestamp', []],
            ['column10', 'date', []],
            ['column11', 'binary', []],
            ['column13', 'string', ['limit' => 10]],
            ['column15', 'smallinteger', []],
            ['column15', 'integer', []],
            ['column23', 'json', []],
        ];
    }

    public function testAddIndex()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));
    }

    public function testAddForeignKey()
    {
        $refTable = new PhinxTable('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new PhinxTable('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testHasDatabase()
    {
        if ($this->config['database'] === ':memory:') {
            $this->markTestSkipped('Skipping hasDatabase() when testing in-memory db.');
        }
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

    public function testAddColumnWithComment()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => $comment = 'Comments from "column1"'])
            ->save();

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');

        foreach ($rows as $row) {
            if ($row['tbl_name'] === 'table1') {
                $sql = $row['sql'];
            }
        }

        $this->assertMatchesRegularExpression('/\/\* Comments from "column1" \*\//', $sql);
    }

    public function testAddIndexTwoTablesSameIndex()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $table2 = new PhinxTable('table2', [], $this->adapter);
        $table2->addColumn('email', 'string')
            ->save();

        $this->assertFalse($table->hasIndex('email'));
        $this->assertFalse($table2->hasIndex('email'));

        $table->addIndex('email')
            ->save();
        $table2->addIndex('email')
            ->save();

        $this->assertTrue($table->hasIndex('email'));
        $this->assertTrue($table2->hasIndex('email'));
    }

    public function testBulkInsertData()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer', ['null' => true])
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
            ->insert(
                [
                    'column1' => '\'value4\'',
                    'column2' => null,
                ]
            )
            ->save();
        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals('\'value4\'', $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertNull($rows[3]['column2']);
    }

    public function testInsertData()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer', ['null' => true])
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
            ->insert(
                [
                    'column1' => '\'value4\'',
                    'column2' => null,
                ]
            )
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals('\'value4\'', $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertNull($rows[3]['column2']);
    }

    public function testBulkInsertDataEnum()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'string', ['null' => true])
            ->addColumn('column3', 'string', ['default' => 'c'])
            ->insert([
                'column1' => 'a',
            ])
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('a', $rows[0]['column1']);
        $this->assertNull($rows[0]['column2']);
        $this->assertEquals('c', $rows[0]['column3']);
    }

    public function testNullWithoutDefaultValue()
    {
        $this->markTestSkipped('Skipping for now. See Github Issue #265.');

        // construct table with default/null combinations
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('aa', 'string', ['null' => true]) // no default value
            ->addColumn('bb', 'string', ['null' => false]) // no default value
            ->addColumn('cc', 'string', ['null' => true, 'default' => 'some1'])
            ->addColumn('dd', 'string', ['null' => false, 'default' => 'some2'])
            ->save();

        // load table info
        $columns = $this->adapter->getColumns('table1');

        $this->assertCount(5, $columns);

        $aa = $columns[1];
        $bb = $columns[2];
        $cc = $columns[3];
        $dd = $columns[4];

        $this->assertEquals('aa', $aa->getName());
        $this->assertTrue($aa->isNull());
        $this->assertNull($aa->getDefault());

        $this->assertEquals('bb', $bb->getName());
        $this->assertFalse($bb->isNull());
        $this->assertNull($bb->getDefault());

        $this->assertEquals('cc', $cc->getName());
        $this->assertTrue($cc->isNull());
        $this->assertEquals('some1', $cc->getDefault());

        $this->assertEquals('dd', $dd->getName());
        $this->assertFalse($dd->isNull());
        $this->assertEquals('some2', $dd->getDefault());
    }

    public function testDumpCreateTable()
    {
        $this->adapter->setOptions(['dryrun' => true]);

        $table = new PhinxTable('table1', [], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, `column1` VARCHAR NOT NULL, `column2` INTEGER NULL, `column3` VARCHAR NULL DEFAULT 'test');
OUTPUT;
        $actualOutput = join("\n", $this->out->messages());
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create table query to the output');
    }

    /**
     * Creates the table "table1".
     * Then sets phinx to dry run mode and inserts a record.
     * Asserts that phinx outputs the insert statement and doesn't insert a record.
     */
    public function testDumpInsert()
    {

        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $this->adapter->setOptions(['dryrun' => true]);
        $this->adapter->insert($table->getTable(), [
            'string_col' => 'test data',
        ]);

        $this->adapter->insert($table->getTable(), [
            'string_col' => null,
        ]);

        $this->adapter->insert($table->getTable(), [
            'int_col' => 23,
        ]);

        $expectedOutput = <<<'OUTPUT'
INSERT INTO `table1` (`string_col`) VALUES ('test data');
INSERT INTO `table1` (`string_col`) VALUES (null);
INSERT INTO `table1` (`int_col`) VALUES (23);
OUTPUT;
        $actualOutput = join("\n", $this->out->messages());
        $actualOutput = preg_replace("/\r\n|\r/", "\n", $actualOutput); // normalize line endings for Windows
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option doesn\'t dump the insert to the output');

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll('assoc');
        $this->assertEquals(0, $res[0]['COUNT(*)']);
    }

    /**
     * Creates the table "table1".
     * Then sets phinx to dry run mode and inserts some records.
     * Asserts that phinx outputs the insert statement and doesn't insert any record.
     */
    public function testDumpBulkinsert()
    {

        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $this->adapter->setOptions(['dryrun' => true]);
        $this->adapter->bulkinsert($table->getTable(), [
            [
                'string_col' => 'test_data1',
                'int_col' => 23,
            ],
            [
                'string_col' => null,
                'int_col' => 42,
            ],
        ]);

        $expectedOutput = <<<'OUTPUT'
INSERT INTO `table1` (`string_col`, `int_col`) VALUES ('test_data1', 23), (null, 42);
OUTPUT;
        $actualOutput = join("\n", $this->out->messages());
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option doesn\'t dump the bulkinsert to the output');

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll('assoc');
        $this->assertEquals(0, $res[0]['COUNT(*)']);
    }

    public function testDumpCreateTableAndThenInsert()
    {
        $this->adapter->setOptions(['dryrun' => true]);

        $table = new PhinxTable('table1', ['id' => false, 'primary_key' => ['column1']], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->save();

        $expectedOutput = 'C';

        $table = new PhinxTable('table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1,
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`column1` VARCHAR NOT NULL, `column2` INTEGER NULL, PRIMARY KEY (`column1`));
INSERT INTO `table1` (`column1`, `column2`) VALUES ('id1', 1);
OUTPUT;
        $actualOutput = join("\n", $this->out->messages());
        $actualOutput = preg_replace("/\r\n|\r/", "\n", $actualOutput); // normalize line endings for Windows
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create and then insert table queries to the output');
    }

    /**
     * Tests interaction with the query builder
     */
    public function testQueryBuilder()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $builder = $this->adapter->getQueryBuilder(Query::TYPE_INSERT);
        $stm = $builder
            ->insert(['string_col', 'int_col'])
            ->into('table1')
            ->values(['string_col' => 'value1', 'int_col' => 1])
            ->values(['string_col' => 'value2', 'int_col' => 2])
            ->execute();

        $this->assertEquals(2, $stm->rowCount());

        $builder = $this->adapter->getQueryBuilder(Query::TYPE_SELECT);
        $stm = $builder
            ->select('*')
            ->from('table1')
            ->where(['int_col >=' => 2])
            ->execute();

        $this->assertEquals(0, $stm->rowCount());
        $this->assertEquals(
            ['id' => 2, 'string_col' => 'value2', 'int_col' => '2'],
            $stm->fetch('assoc')
        );

        $builder = $this->adapter->getQueryBuilder(Query::TYPE_DELETE);
        $stm = $builder
            ->delete('table1')
            ->where(['int_col <' => 2])
            ->execute();

        $this->assertEquals(1, $stm->rowCount());
    }

    public function testQueryWithParams()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
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

    /**
     * Tests adding more than one column to a table
     * that already exists due to adapters having different add column instructions
     */
    public function testAlterTableColumnAdd()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->create();

        $table->addColumn('string_col', 'string', ['default' => '']);
        $table->addColumn('string_col_2', 'string', ['null' => true]);
        $table->addColumn('string_col_3', 'string', ['null' => false]);
        $table->addTimestamps();
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        $expected = [
            ['name' => 'id', 'type' => 'integer', 'default' => null, 'null' => false],
            ['name' => 'string_col', 'type' => 'string', 'default' => '', 'null' => true],
            ['name' => 'string_col_2', 'type' => 'string', 'default' => null, 'null' => true],
            ['name' => 'string_col_3', 'type' => 'string', 'default' => null, 'null' => false],
            ['name' => 'created_at', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'null' => false],
            ['name' => 'updated_at', 'type' => 'timestamp', 'default' => null, 'null' => true],
        ];

        $this->assertEquals(count($expected), count($columns));

        $columnCount = count($columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $this->assertSame($expected[$i]['name'], $columns[$i]->getName(), "Wrong name for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['type'], $columns[$i]->getType(), "Wrong type for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['default'], $columns[$i]->getDefault() instanceof Literal ? (string)$columns[$i]->getDefault() : $columns[$i]->getDefault(), "Wrong default for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['null'], $columns[$i]->getNull(), "Wrong null for {$expected[$i]['name']}");
        }
    }

    public function testAlterTableWithConstraints()
    {
        $table = new PhinxTable('table1', [], $this->adapter);
        $table->create();

        $table2 = new PhinxTable('table2', [], $this->adapter);
        $table2->create();

        $table
            ->addColumn('table2_id', 'integer', ['null' => false])
            ->addForeignKey('table2_id', 'table2', 'id', [
                'delete' => 'SET NULL',
            ]);
        $table->update();

        $table->addColumn('column3', 'string', ['default' => null, 'null' => true]);
        $table->update();

        $columns = $this->adapter->getColumns('table1');
        $expected = [
            ['name' => 'id', 'type' => 'integer', 'default' => null, 'null' => false],
            ['name' => 'table2_id', 'type' => 'integer', 'default' => null, 'null' => false],
            ['name' => 'column3', 'type' => 'string', 'default' => null, 'null' => true],
        ];

        $this->assertEquals(count($expected), count($columns));

        $columnCount = count($columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $this->assertSame($expected[$i]['name'], $columns[$i]->getName(), "Wrong name for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['type'], $columns[$i]->getType(), "Wrong type for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['default'], $columns[$i]->getDefault() instanceof Literal ? (string)$columns[$i]->getDefault() : $columns[$i]->getDefault(), "Wrong default for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['null'], $columns[$i]->getNull(), "Wrong null for {$expected[$i]['name']}");
        }
    }

    /**
     * Tests that operations that trigger implicit table drops will not cause
     * a foreign key constraint violation error.
     */
    public function testAlterTableDoesNotViolateRestrictedForeignKeyConstraint()
    {
        $this->adapter->execute('PRAGMA foreign_keys = ON');

        $articlesTable = new PhinxTable('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new PhinxTable('comments', [], $this->adapter);
        $commentsTable
            ->addColumn('article_id', 'integer')
            ->addForeignKey('article_id', 'articles', 'id', [
                'update' => ForeignKey::RESTRICT,
                'delete' => ForeignKey::RESTRICT,
            ])
            ->insert(['id' => 1, 'article_id' => 1])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey('comments', ['article_id']));

        $articlesTable
            ->addColumn('new_column', 'integer')
            ->update();

        $articlesTable
            ->renameColumn('new_column', 'new_column_renamed')
            ->update();

        $articlesTable
            ->changeColumn('new_column_renamed', 'integer', [
                'default' => 1,
            ])
            ->update();

        $articlesTable
            ->removeColumn('new_column_renamed')
            ->update();

        $articlesTable
            ->addIndex('id', ['name' => 'ID_IDX'])
            ->update();

        $articlesTable
            ->removeIndex('id')
            ->update();

        $articlesTable
            ->addForeignKey('id', 'comments', 'id')
            ->update();

        $articlesTable
            ->dropForeignKey('id')
            ->update();

        $articlesTable
            ->addColumn('id2', 'integer')
            ->addIndex('id', ['unique' => true])
            ->changePrimaryKey('id2')
            ->update();
    }

    /**
     * Tests that foreign key constraint violations introduced around the table
     * alteration process (being it implicitly by the process itself or by the user)
     * will trigger an error accordingly.
     */
    public function testAlterTableDoesViolateForeignKeyConstraintOnTargetTableChange()
    {
        $articlesTable = new PhinxTable('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new PhinxTable('comments', [], $this->adapter);
        $commentsTable
            ->addColumn('article_id', 'integer')
            ->addForeignKey('article_id', 'articles', 'id', [
                'update' => ForeignKey::RESTRICT,
                'delete' => ForeignKey::RESTRICT,
            ])
            ->insert(['id' => 1, 'article_id' => 1])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey('comments', ['article_id']));

        $this->adapter->execute('PRAGMA foreign_keys = OFF');
        $this->adapter->execute('DELETE FROM articles');
        $this->adapter->execute('PRAGMA foreign_keys = ON');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integrity constraint violation: FOREIGN KEY constraint on `comments` failed.');

        $articlesTable
            ->addColumn('new_column', 'integer')
            ->update();
    }

    public function testLiteralSupport()
    {
        $createQuery = <<<'INPUT'
CREATE TABLE `test` (`real_col` DECIMAL)
INPUT;
        $this->adapter->execute($createQuery);
        $table = new PhinxTable('test', [], $this->adapter);
        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertEquals(Literal::from('decimal'), array_pop($columns)->getType());
    }

    /**
     * @covers \Migrations\Db\Adapter\SqliteAdapter::hasPrimaryKey
     */
    public function testHasNamedPrimaryKey()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->adapter->hasPrimaryKey('t', [], 'named_constraint');
    }

    /** @covers \Migrations\Db\Adapter\SqliteAdapter::getColumnTypes */
    public function testGetColumnTypes()
    {
        $columnTypes = $this->adapter->getColumnTypes();
        $expected = [
            SqliteAdapter::PHINX_TYPE_BIG_INTEGER,
            SqliteAdapter::PHINX_TYPE_BINARY,
            SqliteAdapter::PHINX_TYPE_BLOB,
            SqliteAdapter::PHINX_TYPE_BOOLEAN,
            SqliteAdapter::PHINX_TYPE_CHAR,
            SqliteAdapter::PHINX_TYPE_DATE,
            SqliteAdapter::PHINX_TYPE_DATETIME,
            SqliteAdapter::PHINX_TYPE_DECIMAL,
            SqliteAdapter::PHINX_TYPE_DOUBLE,
            SqliteAdapter::PHINX_TYPE_FLOAT,
            SqliteAdapter::PHINX_TYPE_INTEGER,
            SqliteAdapter::PHINX_TYPE_JSON,
            SqliteAdapter::PHINX_TYPE_JSONB,
            SqliteAdapter::PHINX_TYPE_SMALL_INTEGER,
            SqliteAdapter::PHINX_TYPE_STRING,
            SqliteAdapter::PHINX_TYPE_TEXT,
            SqliteAdapter::PHINX_TYPE_TIME,
            SqliteAdapter::PHINX_TYPE_UUID,
            SqliteAdapter::PHINX_TYPE_BINARYUUID,
            SqliteAdapter::PHINX_TYPE_TIMESTAMP,
            SqliteAdapter::PHINX_TYPE_TINY_INTEGER,
            SqliteAdapter::PHINX_TYPE_VARBINARY,
        ];
        sort($columnTypes);
        sort($expected);

        $this->assertEquals($expected, $columnTypes);
    }

    /**
     * @dataProvider provideColumnTypesForValidation
     * @covers \Phinx\Db\Adapter\SqliteAdapter::isValidColumnType
     */
    public function testIsValidColumnType($phinxType, $exp)
    {
        $col = (new PhinxColumn())->setType($phinxType);
        $this->assertSame($exp, $this->adapter->isValidColumnType($col));
    }

    public static function provideColumnTypesForValidation()
    {
        return [
            [SqliteAdapter::PHINX_TYPE_BIG_INTEGER, true],
            [SqliteAdapter::PHINX_TYPE_BINARY, true],
            [SqliteAdapter::PHINX_TYPE_BLOB, true],
            [SqliteAdapter::PHINX_TYPE_BOOLEAN, true],
            [SqliteAdapter::PHINX_TYPE_CHAR, true],
            [SqliteAdapter::PHINX_TYPE_DATE, true],
            [SqliteAdapter::PHINX_TYPE_DATETIME, true],
            [SqliteAdapter::PHINX_TYPE_DOUBLE, true],
            [SqliteAdapter::PHINX_TYPE_FLOAT, true],
            [SqliteAdapter::PHINX_TYPE_INTEGER, true],
            [SqliteAdapter::PHINX_TYPE_JSON, true],
            [SqliteAdapter::PHINX_TYPE_JSONB, true],
            [SqliteAdapter::PHINX_TYPE_SMALL_INTEGER, true],
            [SqliteAdapter::PHINX_TYPE_STRING, true],
            [SqliteAdapter::PHINX_TYPE_TEXT, true],
            [SqliteAdapter::PHINX_TYPE_TIME, true],
            [SqliteAdapter::PHINX_TYPE_UUID, true],
            [SqliteAdapter::PHINX_TYPE_TIMESTAMP, true],
            [SqliteAdapter::PHINX_TYPE_VARBINARY, true],
            [SqliteAdapter::PHINX_TYPE_BIT, false],
            [SqliteAdapter::PHINX_TYPE_CIDR, false],
            [SqliteAdapter::PHINX_TYPE_DECIMAL, true],
            [SqliteAdapter::PHINX_TYPE_ENUM, false],
            [SqliteAdapter::PHINX_TYPE_FILESTREAM, false],
            [SqliteAdapter::PHINX_TYPE_GEOMETRY, false],
            [SqliteAdapter::PHINX_TYPE_INET, false],
            [SqliteAdapter::PHINX_TYPE_INTERVAL, false],
            [SqliteAdapter::PHINX_TYPE_LINESTRING, false],
            [SqliteAdapter::PHINX_TYPE_MACADDR, false],
            [SqliteAdapter::PHINX_TYPE_POINT, false],
            [SqliteAdapter::PHINX_TYPE_POLYGON, false],
            [SqliteAdapter::PHINX_TYPE_SET, false],
            [PhinxLiteral::from('someType'), true],
            ['someType', false],
        ];
    }

    /** @covers \Phinx\Db\Adapter\SqliteAdapter::getSchemaName
     * @covers \Phinx\Db\Adapter\SqliteAdapter::getTableInfo
     * @covers \Phinx\Db\Adapter\SqliteAdapter::getColumns
     */
    public function testGetColumns()
    {
        $conn = $this->adapter->getConnection();
        $conn->execute('create table t(a integer, b text, c char(5), d integer(12,6), e integer not null, f integer null)');
        $exp = [
            ['name' => 'a', 'type' => 'integer', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'b', 'type' => 'text', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'c', 'type' => 'char', 'null' => true, 'limit' => 5, 'precision' => 5, 'scale' => null],
            ['name' => 'd', 'type' => 'integer', 'null' => true, 'limit' => 12, 'precision' => 12, 'scale' => 6],
            ['name' => 'e', 'type' => 'integer', 'null' => false, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'f', 'type' => 'integer', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
        ];
        $act = $this->adapter->getColumns('t');
        $this->assertCount(count($exp), $act);
        foreach ($exp as $index => $data) {
            $this->assertInstanceOf(PhinxColumn::class, $act[$index]);
            foreach ($data as $key => $value) {
                $m = 'get' . ucfirst($key);
                $this->assertEquals($value, $act[$index]->$m(), "Parameter '$key' of column at index $index did not match expectations.");
            }
        }
    }

    public function testForeignKeyReferenceCorrectAfterRenameColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToRename = 'columnToRename';
        $refTableRenamedColumn = 'renamedColumn';
        $refTable = new PhinxTable('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRename, 'string')->save();

        $table = new PhinxTable('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->renameColumn($refTableColumnToRename, $refTableRenamedColumn)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertTrue($this->adapter->hasColumn($refTable->getName(), $refTableRenamedColumn));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangeColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToChange = 'columnToChange';
        $refTable = new PhinxTable('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToChange, 'string')->save();

        $table = new PhinxTable('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->changeColumn($refTableColumnToChange, 'text')->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertEquals('text', $this->adapter->getColumns($refTable->getName())[1]->getType());

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterRemoveColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToRemove = 'columnToRemove';
        $refTable = new PhinxTable('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRemove, 'string')->save();

        $table = new PhinxTable('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->removeColumn($refTableColumnToRemove)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertFalse($this->adapter->hasColumn($refTable->getName(), $refTableColumnToRemove));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangePrimaryKey()
    {
        $refTableColumnAdditionalId = 'additional_id';
        $refTableColumnId = 'ref_table_id';
        $refTable = new PhinxTable('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnAdditionalId, 'integer')->save();

        $table = new PhinxTable('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable
            ->addIndex('id', ['unique' => true])
            ->changePrimaryKey($refTableColumnAdditionalId)
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertTrue($this->adapter->getColumns($refTable->getName())[1]->getIdentity());

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }
}
