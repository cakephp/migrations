<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use BadMethodCallException;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Migrations\Db\Adapter\SqliteAdapter;
use Migrations\Db\Expression;
use Migrations\Db\Literal;
use Migrations\Db\Table;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionObject;
use RuntimeException;

class SqliteAdapterTest extends TestCase
{
    private array $config;

    private StubConsoleOutput $out;

    private ConsoleIo $io;

    private SqliteAdapter $adapter;

    protected function setUp(): void
    {
        /** @var array<string, mixed> $config */
        $config = ConnectionManager::getConfig('test');
        if ($config['scheme'] !== 'sqlite') {
            $this->markTestSkipped('SQLite tests disabled.');
        }
        $this->config = [
            'adapter' => 'sqlite',
            'suffix' => '',
            'connection' => ConnectionManager::get('test'),
            'database' => $config['database'],
        ];
        $io = $this->getConsoleIo();
        $this->adapter = new SqliteAdapter($this->config, $io);

        if ($config['database'] !== ':memory:') {
            // ensure the database is empty for each test
            $this->adapter->dropDatabase($config['database']);
            $this->adapter->createDatabase($config['database']);
        }

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
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

    protected function tearDown(): void
    {
        unset($this->adapter, $this->out, $this->io);
    }

    public function testGetConnection(): void
    {
        error_reporting(E_ALL);
        $this->deprecated(function (): void {
            $connection = $this->adapter->getConnection();
            $this->assertInstanceOf(Connection::class, $connection);
            $this->assertSame($connection, $this->adapter->getDecoratedConnection());
        });
    }

    public function testBeginTransaction(): void
    {
        $this->adapter->beginTransaction();

        $this->assertTrue(
            $this->adapter->getConnection()->inTransaction(),
            'Underlying PDO instance did not detect new transaction',
        );
    }

    public function testRollbackTransaction(): void
    {
        $this->adapter->beginTransaction();
        $this->adapter->rollbackTransaction();

        $this->assertFalse(
            $this->adapter->getConnection()->inTransaction(),
            'Underlying PDO instance did not detect rolled back transaction',
        );
    }

    public function testCommitTransactionTransaction(): void
    {
        $this->adapter->beginTransaction();
        $this->adapter->commitTransaction();

        $this->assertFalse(
            $this->adapter->getConnection()->inTransaction(),
            "Underlying PDO instance didn't detect committed transaction",
        );
    }

    public function testCreatingTheSchemaTableOnConnect(): void
    {
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
        $this->assertFalse($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->disconnect();
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
    }

    public function testSchemaTableIsCreatedWithPrimaryKey(): void
    {
        $this->adapter->connect();
        new Table($this->adapter->getSchemaTableName(), [], $this->adapter);
        $this->assertTrue($this->adapter->hasIndex($this->adapter->getSchemaTableName(), ['version']));
    }

    public function testQuoteTableName(): void
    {
        $this->assertEquals('"test_table"', $this->adapter->quoteTableName('test_table'));
    }

    public function testQuoteColumnName(): void
    {
        $this->assertEquals('"test_column"', $this->adapter->quoteColumnName('test_column'));
    }

    public function testCreateTable(): void
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

    public function testCreateTableCustomIdColumn(): void
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

        //ensure the primary key is not nullable
        /** @var \Migrations\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertTrue($idColumn->getIdentity());
        $this->assertFalse($idColumn->isNull());
    }

    public function testCreateTableIdentityIdColumn(): void
    {
        $table = new Table('ntable', ['id' => false, 'primary_key' => ['custom_id']], $this->adapter);
        $table->addColumn('custom_id', 'integer', ['identity' => true])
            ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));

        /** @var \Migrations\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertTrue($idColumn->getIdentity());
    }

    public function testCreateTableWithNoPrimaryKey(): void
    {
        $options = [
            'id' => false,
        ];
        $table = new Table('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }

    public function testCreateTableWithMultiplePrimaryKeys(): void
    {
        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id'],
        ];
        $table = new Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['USER_ID', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'USER_ID']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsUuid(): void
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'uuid')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsBinaryUuid(): void
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'binaryuuid')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
    }

    public function testCreateTableWithMultipleIndexes(): void
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

    public function testCreateTableWithUniqueIndexes(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['unique' => true])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndexes(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailindex'])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertTrue($this->adapter->hasIndexByName('table1', 'myemailindex'));
    }

    public function testCreateTableWithForeignKey(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer');
        $table->addForeignKey('ref_table_id', 'ref_table', 'id');
        $table->save();

        $this->assertTrue($this->adapter->hasTable($table->getName()));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testCreateTableWithIndexesAndForeignKey(): void
    {
        $refTable = new Table('tbl_master', [], $this->adapter);
        $refTable->create();

        $table = new Table('tbl_child', [], $this->adapter);
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
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_master_id'],
            )
            ->create();

        $this->assertTrue($this->adapter->hasIndex('tbl_child', 'column2'));
        $this->assertTrue($this->adapter->hasIndex('tbl_child', ['column1', 'column2']));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['master_id']));

        $row = $this->adapter->fetchRow(
            "SELECT * FROM sqlite_master WHERE `type` = 'table' AND `tbl_name` = 'tbl_child'",
        );
        $this->assertStringContainsString(
            'CONSTRAINT "fk_master_id" FOREIGN KEY ("master_id") REFERENCES "tbl_master" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION',
            $row['sql'],
        );
    }

    public function testCreateTableWithoutAutoIncrementingPrimaryKeyAndWithForeignKey(): void
    {
        $refTable = (new Table('tbl_master', ['id' => false, 'primary_key' => 'id'], $this->adapter))
            ->addColumn('id', 'text');
        $refTable->create();

        $table = (new Table('tbl_child', ['id' => false, 'primary_key' => 'master_id'], $this->adapter))
            ->addColumn('master_id', 'text')
            ->addForeignKey(
                'master_id',
                'tbl_master',
                'id',
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_master_id'],
            );
        $table->create();

        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['master_id']));

        $row = $this->adapter->fetchRow(
            "SELECT * FROM sqlite_master WHERE \"type\" = 'table' AND \"tbl_name\" = 'tbl_child'",
        );
        $this->assertStringContainsString(
            'CONSTRAINT "fk_master_id" FOREIGN KEY ("master_id") REFERENCES "tbl_master" ("id") ON DELETE NO ACTION ON UPDATE NO ACTION',
            $row['sql'],
        );
    }

    public function testCreateTableIndexWithWhere(): void
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $index = new Index();
        $index->setColumns('email')
            ->setType(Index::UNIQUE)
            ->setWhere('is_verified = true');

        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('is_verified', 'boolean')
              ->addIndex($index)
              ->save();
        $queries = $this->out->messages();
        $indexQuery = '';
        foreach ($queries as $query) {
            if (str_contains($query, 'CREATE UNIQUE INDEX "table1_email_index"')) {
                $indexQuery = $query;
                break;
            }
        }
        $this->assertStringContainsString('CREATE UNIQUE INDEX "table1_email_index"', $indexQuery);
        $this->assertStringContainsString('("email" ASC) WHERE is_verified = true', $indexQuery);
    }

    public function testAddPrimaryKey(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->addPrimaryKey('id')
            ->save();

        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['id']));
    }

    public function testChangePrimaryKey(): void
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
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

    public function testChangePrimaryKeyNonInteger(): void
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
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

    public function testChangePrimaryKeyWithoutAutoIncrement(): void
    {
        // Create table with id_1 as PK without AUTOINCREMENT keyword
        $this->adapter->execute('CREATE TABLE table1 (id_1 INTEGER NOT NULL PRIMARY KEY, id_2 INTEGER NOT NULL)');

        // Verify initial SQL does not have AUTOINCREMENT
        $result = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE type='table' AND name='table1'");
        $this->assertStringNotContainsString('AUTOINCREMENT', $result['sql']);

        // Change primary key to id_2
        $table = new Table('table1', [], $this->adapter);
        $table->changePrimaryKey('id_2')->save();

        // Verify primary key changed
        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['id_1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['id_2']));

        // Verify the SQL does NOT have AUTOINCREMENT added to id_2
        $result = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE type='table' AND name='table1'");
        $this->assertStringNotContainsString('AUTOINCREMENT', $result['sql'], 'AUTOINCREMENT should not be added when changing PK to a column that did not have it');
    }

    public function testChangePrimaryKeyFromAutoIncrementColumn(): void
    {
        // Create table with id_1 as PK with AUTOINCREMENT
        $this->adapter->execute('CREATE TABLE table1 (id_1 INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, id_2 INTEGER NOT NULL)');

        // Verify initial SQL has AUTOINCREMENT
        $result = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE type='table' AND name='table1'");
        $this->assertStringContainsString('AUTOINCREMENT', $result['sql']);

        // Change primary key to id_2 (should NOT get AUTOINCREMENT since id_2 doesn't have it)
        $table = new Table('table1', [], $this->adapter);
        $table->changePrimaryKey('id_2')->save();

        // Verify primary key changed
        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['id_1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['id_2']));

        // Verify the SQL does NOT have AUTOINCREMENT on id_2
        // (id_1 lost its AUTOINCREMENT when PK was dropped, and id_2 never had it)
        $result = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE type='table' AND name='table1'");
        $this->assertStringNotContainsString('AUTOINCREMENT', $result['sql'], 'AUTOINCREMENT should not be added when changing PK to a column that never had it');
    }

    public function testDropPrimaryKey(): void
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testAddMultipleColumnPrimaryKeyFails(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $this->expectException(InvalidArgumentException::class);

        $table
            ->changePrimaryKey(['column1', 'column2'])
            ->save();
    }

    public function testChangeCommentFails(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $this->expectException(BadMethodCallException::class);

        $table
            ->changeComment('comment1')
            ->save();
    }

    public function testRenameTable(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));
        $this->adapter->renameTable('table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }

    public function testAddColumn(): void
    {
        $table = new Table('table1', [], $this->adapter);
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

    public function testAddColumnWithDefaultValue(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("'test'", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultZero(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertNotNull($rows[1]['dflt_value']);
        $this->assertEquals('0', $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultEmptyString(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("''", $rows[1]['dflt_value']);
    }

    public function testAddDecimalWithPrecisionAndScale(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('number', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('number2', 'decimal', ['limit' => 12])
            ->addColumn('number3', 'decimal')
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'number') {
                $this->assertEquals('10', $column->getPrecision());
                $this->assertEquals('2', $column->getScale());
            }

            if ($column->getName() === 'number2') {
                $this->assertEquals('12', $column->getPrecision());
                $this->assertEquals('0', $column->getScale());
            }
        }
    }

    public static function irregularCreateTableProvider(): array
    {
        return [
            ["CREATE TABLE \"users\"\n( \"id\" INTEGER NOT NULL )", ['id', 'foo']],
            ['CREATE TABLE users   (    id INTEGER NOT NULL )', ['id', 'foo']],
            ['CREATE TABLE `users` (`id` INTEGER NOT NULL )', ['id', 'foo']],
            ["CREATE TABLE [users]\n(\nid INTEGER NOT NULL)", ['id', 'foo']],
            ["CREATE TABLE \"users\" ([id] \n INTEGER NOT NULL\n, \"bar\" INTEGER)", ['id', 'bar', 'foo']],
        ];
    }

    #[DataProvider('irregularCreateTableProvider')]
    public function testAddColumnToIrregularCreateTableStatements(string $createTableSql, array $expectedColumns): void
    {
        $this->adapter->execute($createTableSql);
        $table = new Table('users', [], $this->adapter);
        $table->addColumn('foo', 'string');
        $table->update();

        $columns = $this->adapter->getColumns('users');
        $columnCount = count($columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $this->assertEquals($expectedColumns[$i], $columns[$i]->getName());
        }
    }

    public function testRenameColumn(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenamingANonExistentColumn(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The specified column doesn't exist: column2");
        $this->adapter->renameColumn('t', 'column2', 'column1');
    }

    public function testRenameColumnWithIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
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

    public function testRenameColumnWithUniqueIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
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

    public function testRenameColumnWithCompositeIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
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
    public function testRenameColumnWithIndexMatchingTheTableName(): void
    {
        $table = new Table('indexcol', [], $this->adapter);
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
    public function testRenameColumnWithIndexColumnPartialMatch(): void
    {
        $table = new Table('t', [], $this->adapter);
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

    public function testRenameColumnWithIndexColumnRequiringQuoting(): void
    {
        $table = new Table('t', [], $this->adapter);
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
    public function testRenameColumnWithExpressionIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t ("indexcol", ABS(indexcol))');

        $this->assertTrue($this->adapter->hasIndexByName('t', 'custom_idx'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: indexcol');

        $table->renameColumn('indexcol', 'newindexcol')->update();
        $this->assertTrue($this->adapter->hasIndexByName('t', 'custom_idx'));
    }

    /**
     * Index SQL is mostly returned as-is, hence custom indices can contain
     * a wide variety of formats.
     */
    public static function customIndexSQLDataProvider(): array
    {
        return [
            [
                'CREATE INDEX test_idx ON t(indexcol);',
                'CREATE INDEX test_idx ON t("newindexcol")',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol`);',
                'CREATE INDEX test_idx ON t("newindexcol")',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol");',
                'CREATE INDEX test_idx ON t("newindexcol")',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol]);',
                'CREATE INDEX test_idx ON t("newindexcol")',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol ASC);',
                'CREATE INDEX test_idx ON t("newindexcol" ASC)',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol` ASC);',
                'CREATE INDEX test_idx ON t("newindexcol" ASC)',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol" DESC);',
                'CREATE INDEX test_idx ON t("newindexcol" DESC)',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol] DESC);',
                'CREATE INDEX test_idx ON t("newindexcol" DESC)',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol COLLATE BINARY);',
                'CREATE INDEX test_idx ON t("newindexcol" COLLATE BINARY)',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol COLLATE BINARY ASC);',
                'CREATE INDEX test_idx ON t("newindexcol" COLLATE BINARY ASC)',
            ],
            [
                '
                    cReATE uniQUE inDEx
                        iF   nOT   ExISts
                            main.test_idx   on   t  (
                                ( ((
                                    inDEXcoL
                                ) )) COLLATE   BINARY   ASC
                            );
                ',
                'CREATE UNIQUE INDEX test_idx   on   t  (
                                ( ((
                                    "newindexcol"
                                ) )) COLLATE   BINARY   ASC
                            )',
            ],
        ];
    }

    /**
     * @param string $indexSQL Index creation SQL
     * @param string $newIndexSQL Expected new index creation SQL
     */
    #[DataProvider('customIndexSQLDataProvider')]
    public function testRenameColumnWithCustomIndex(string $indexSQL, string $newIndexSQL): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $index = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE \"type\" = 'index' AND name = 'test_idx'");
        $this->assertSame($newIndexSQL, $index['sql']);
    }

    /**
     * Index SQL is mostly returned as-is, hence custom indices can contain
     * a wide variety of formats.
     */
    public static function customCompositeIndexSQLDataProvider(): array
    {
        return [
            [
                'CREATE INDEX test_idx ON t(indexcol1, indexcol2, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1, "newindexcol", indexcol3)',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol1`, `indexcol2`, `indexcol3`);',
                'CREATE INDEX test_idx ON t(`indexcol1`, "newindexcol", `indexcol3`)',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol1", "indexcol2", "indexcol3");',
                'CREATE INDEX test_idx ON t("indexcol1", "newindexcol", "indexcol3")',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol1], [indexcol2], [indexcol3]);',
                'CREATE INDEX test_idx ON t([indexcol1], "newindexcol", [indexcol3])',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol1 ASC, indexcol2 DESC, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1 ASC, "newindexcol" DESC, indexcol3)',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol1` ASC, `indexcol2` DESC, `indexcol3`);',
                'CREATE INDEX test_idx ON t(`indexcol1` ASC, "newindexcol" DESC, `indexcol3`)',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol1" ASC, "indexcol2" DESC, "indexcol3");',
                'CREATE INDEX test_idx ON t("indexcol1" ASC, "newindexcol" DESC, "indexcol3")',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol1] ASC, [indexcol2] DESC, [indexcol3]);',
                'CREATE INDEX test_idx ON t([indexcol1] ASC, "newindexcol" DESC, [indexcol3])',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY, indexcol2 COLLATE NOCASE, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY, "newindexcol" COLLATE NOCASE, indexcol3)',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY ASC, indexcol2 COLLATE NOCASE DESC, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY ASC, "newindexcol" COLLATE NOCASE DESC, indexcol3)',
            ],
            [
                '
                    cReATE uniQUE inDEx
                        iF   nOT   ExISts
                            main.test_idx   on   t  (
                                inDEXcoL1 ,
                                ( ((
                                    inDEXcoL2
                                ) )) COLLATE   BINARY   ASC ,
                                inDEXcoL3
                            );
                ',
                'CREATE UNIQUE INDEX test_idx   on   t  (
                                inDEXcoL1 ,
                                ( ((
                                    "newindexcol"
                                ) )) COLLATE   BINARY   ASC ,
                                inDEXcoL3
                            )',
            ],
        ];
    }

    /**
     * Index SQL is mostly returned as-is, hence custom indices can contain
     * a wide variety of formats.
     *
     * @param string $indexSQL Index creation SQL
     * @param string $newIndexSQL Expected new index creation SQL
     */
    #[DataProvider('customCompositeIndexSQLDataProvider')]
    public function testRenameColumnWithCustomCompositeIndex(string $indexSQL, string $newIndexSQL): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addColumn('indexcol3', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol', 'indexcol3']));

        $table->renameColumn('indexcol2', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol', 'indexcol3']));

        $index = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'test_idx'");
        $this->assertSame($index['sql'], $newIndexSQL);
    }

    public function testChangeColumn(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new Column();
        $newColumn1->setName('column1');
        $newColumn1->setType('string');

        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn2 = new Column();
        $newColumn2->setName('column2')
            ->setType('string');
        $table->changeColumn('column1', $newColumn2)->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testChangeColumnDefaultValue(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $newColumn1 = new Column();
        $newColumn1
            ->setName('column1')
            ->setDefault('test1')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');

        $this->assertEquals("'test1'", $rows[1]['dflt_value']);
    }

    public function testChangeColumnWithForeignKey(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('another_table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $table->changeColumn('ref_table_id', 'float')->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testChangeColumnWithIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex(
                'indexcol',
                ['unique' => true],
            )
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->changeColumn('indexcol', 'integer', ['null' => false])->update();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testChangeColumnWithTrigger(): void
    {
        $table = new Table('t', [], $this->adapter);
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
            "SELECT * FROM sqlite_master WHERE \"type\" = 'trigger' AND tbl_name = 't'",
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('trigger', $rows[0]['type']);
        $this->assertEquals('update_t_othercol', $rows[0]['name']);
        $this->assertEquals($triggerSQL, $rows[0]['sql']);

        $table->changeColumn('triggercol', 'integer', ['null' => false])->update();

        $rows = $this->adapter->fetchAll(
            "SELECT * FROM sqlite_master WHERE \"type\" = 'trigger' AND tbl_name = 't'",
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('trigger', $rows[0]['type']);
        $this->assertEquals('update_t_othercol', $rows[0]['name']);
        $this->assertEquals($triggerSQL, $rows[0]['sql']);
    }

    public function testChangeColumnDefaultToZero(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault(0)
            ->setName('column1')
            ->setType('integer');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertEquals('0', $rows[1]['dflt_value']);
    }

    public function testChangeColumnDefaultToNull(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault(null)
            ->setName('column1')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertNull($rows[1]['dflt_value']);
    }

    public function testChangeColumnWithCommasInCommentsOrDefaultValue(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'one, two or three', 'comment' => 'three, two or one'])
            ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault('another default')
            ->setName('column1')
            ->setComment('another comment')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $cols = $this->adapter->getColumns('t');
        $this->assertEquals('another default', (string)$cols[1]->getDefault());
    }

    #[DataProvider('columnCreationArgumentProvider')]
    public function testDropColumn(array $columnCreationArgs): void
    {
        $table = new Table('t', [], $this->adapter);
        $columnName = $columnCreationArgs[0];
        $table->addColumn(...$columnCreationArgs);
        $table->save();
        $this->assertTrue($this->adapter->hasColumn('t', $columnName));

        $table->removeColumn($columnName)->save();

        $this->assertFalse($this->adapter->hasColumn('t', $columnName));
    }

    public function testDropColumnWithIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testDropColumnWithUniqueIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol', ['unique' => true])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testDropColumnWithCompositeIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
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
    public function testDropColumnWithIndexMatchingTheTableName(): void
    {
        $table = new Table('indexcol', [], $this->adapter);
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
    public function testDropColumnWithIndexColumnPartialMatch(): void
    {
        $table = new Table('t', [], $this->adapter);
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
    public function testDropColumnWithExpressionIndex(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (ABS(indexcol))');

        $this->assertTrue($this->adapter->hasIndexByName('t', 'custom_idx'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: indexcol');

        $table->removeColumn('indexcol')->update();
    }

    /**
     * @param string $indexSQL Index creation SQL
     * @param string $newIndexSQL Expected new index creation SQL
     */
    #[DataProvider('customIndexSQLDataProvider')]
    public function testDropColumnWithCustomIndex(string $indexSQL, string $newIndexSQL): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    /**
     * @param string $indexSQL Index creation SQL
     * @param string $newIndexSQL Expected new index creation SQL
     */
    #[DataProvider('customCompositeIndexSQLDataProvider')]
    public function testDropColumnWithCustomCompositeIndex(string $indexSQL, string $newIndexSQL): void
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addColumn('indexcol3', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol3']));

        $table->removeColumn('indexcol2')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol3']));
    }

    public static function columnCreationArgumentProvider(): array
    {
        return [
            [['column1', 'string']],
            [['profile_colour', 'integer']],
        ];
    }

    public static function columnsProvider(): array
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

    public function testAddIndex(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));
    }

    public function testDropIndex(): void
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

        // single column index with name specified
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

    public function testDropIndexByName(): void
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
            ->addIndex(['fname', 'lname'], ['name' => 'twocolumnindex'])
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));
    }

    public function testAddForeignKey(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKey(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')
            ->addIndex(['field1'], ['unique' => true])
            ->save();

        $table = new Table('another_table', [], $this->adapter);
        $opts = [
            'update' => 'CASCADE',
            'delete' => 'CASCADE',
        ];
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addColumn('ref_table_field', 'string')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->addForeignKey(['ref_table_field'], 'ref_table', ['field1'], $opts)
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_field']));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_field']);
        $this->assertTrue($this->adapter->hasTable($table->getName()));
    }

    public function testDropForeignKeyWithQuoteVariants(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')
            ->addIndex(['field1'], ['unique' => true])
            ->save();

        $this->adapter->execute("
            CREATE TABLE `table` (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                ref_no_quotes INTEGER NOT NULL,
                ref_no_space INTEGER NOT NULL,
                ref_lots_of_space INTEGER NOT NULL,
                FOREIGN KEY (ref_no_quotes) REFERENCES `ref_table` (`id`),
                FOREIGN KEY(`ref_no_space`,`ref_no_space`)REFERENCES`ref_table`(`id`,`id`),
                foreign      KEY
                    ( `ref_lots_of_space`		,`ref_lots_of_space`    )
                        REFErences   `ref_table`  (`id`    ,	`id`)
            )
        ");

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_no_quotes']));
        $this->adapter->dropForeignKey('table', ['ref_no_quotes']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_no_quotes']));

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_no_space', 'ref_no_space']));
        $this->adapter->dropForeignKey('table', ['ref_no_space', 'ref_no_space']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_no_space', 'ref_no_space']));

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_lots_of_space', 'ref_lots_of_space']));
        $this->adapter->dropForeignKey('table', ['ref_lots_of_space', 'ref_lots_of_space']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_lots_of_space', 'ref_lots_of_space']));
    }

    public function testDropForeignKeyWithMultipleColumns(): void
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
                ['id', 'field1'],
            )
            ->addForeignKey(
                ['ref_table_field1', 'ref_table_id'],
                'ref_table',
                ['field1', 'id'],
            )
            ->addForeignKey(
                ['ref_table_id', 'ref_table_field1', 'ref_table_field2'],
                'ref_table',
                ['id', 'field1', 'field2'],
            )
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->assertTrue(
            $this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1', 'ref_table_field2']),
            'dropForeignKey() should only affect foreign keys that comprise of exactly the given columns',
        );
        $this->assertTrue(
            $this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']),
            'dropForeignKey() should only affect foreign keys that comprise of columns in exactly the given order',
        );

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']));
        $this->adapter->dropForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']));
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

    #[DataProvider('nonExistentForeignKeyColumnsProvider')]
    public function testDropForeignKeyByNonExistentKeyColumns(array $columns): void
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
                ['id', 'field1'],
            )
            ->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'No foreign key on column(s) `%s` exists',
            implode(', ', $columns),
        ));

        $this->adapter->dropForeignKey($table->getName(), $columns);
    }

    public function testDropForeignKeyCaseInsensitivity(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('another_table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyByName(): void
    {
        $this->expectExceptionMessage('SQLite does not have named foreign keys');
        $this->expectException(BadMethodCallException::class);

        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $key = (new ForeignKey())
            ->setName('my_constraint')
            ->setColumns(['ref_table_id'])
            ->setReferencedTable('ref_table')
            ->setReferencedColumns(['id']);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey($key)
            ->save();

        $this->adapter->dropForeignKey($table->getName(), [], 'my_constraint');
    }

    public function testHasDatabase(): void
    {
        if ($this->config['database'] === ':memory:') {
            $this->markTestSkipped('Skipping hasDatabase() when testing in-memory db.');
        }
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase($this->config['database']));
    }

    public function testDropDatabase(): void
    {
        $this->assertFalse($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->createDatabase('phinx_temp_database');
        $this->assertTrue($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->dropDatabase('phinx_temp_database');
    }

    public function testAddColumnWithComment(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => 'Comments from "column1"'])
            ->save();

        $rows = $this->adapter->fetchAll('select * from sqlite_master where "type" = \'table\'');

        foreach ($rows as $row) {
            if ($row['tbl_name'] === 'table1') {
                $sql = $row['sql'];
            }
        }

        $this->assertMatchesRegularExpression('/\/\* Comments from "column1" \*\//', $sql);
    }

    public function testAddColumnTableWithConstraint(): void
    {
        $this->adapter->execute('PRAGMA foreign_keys = ON');
        $roles = new Table('constraint_roles', [], $this->adapter);
        $roles->addColumn('name', 'string')
            ->save();
        $users = new Table('constraint_users', [], $this->adapter);
        $users->addColumn('username', 'string')
            ->addColumn('role_id', 'integer', ['null' => false])
            ->addForeignKey(['role_id'], $roles->getTable(), ['id'])
            ->save();

        $this->adapter->insert($roles->getTable(), ['name' => 'admin']);
        $this->adapter->insert($users->getTable(), ['username' => 'test', 'role_id' => 1]);

        $updatedRoles = new Table($roles->getName(), [], $this->adapter);
        // This should fail, but passes locally :(
        $updatedRoles
            ->addColumn('description', 'string', ['default' => 'short desc'])
            ->update();
        $res = $this->adapter->fetchAll("select * from sqlite_master where type = 'table'");
        $res = $this->adapter->fetchRow('select * from constraint_roles LIMIT 1');
        $this->assertArrayHasKey('description', $res, 'Should have new column in output');
        $this->assertEquals('short desc', $res['description']);
    }

    public function testAddIndexTwoTablesSameIndex(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $table2 = new Table('table2', [], $this->adapter);
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

    public function testBulkInsertData(): void
    {
        $table = new Table('table1', [], $this->adapter);
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
                ],
            )
            ->insert(
                [
                    'column1' => "'value4'",
                    'column2' => null,
                ],
            )
            ->save();
        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals("'value4'", $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertNull($rows[3]['column2']);
    }

    public function testBulkInsertLiteral(): void
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
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $rows[0]['column2']);
        $this->assertEquals('2024-01-01 00:00:00', $rows[1]['column2']);
        $this->assertEquals('2025-01-01 00:00:00', $rows[2]['column2']);
    }

    public function testInsertData(): void
    {
        $table = new Table('table1', [], $this->adapter);
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
                ],
            )
            ->insert(
                [
                    'column1' => "'value4'",
                    'column2' => null,
                ],
            )
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals("'value4'", $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertNull($rows[3]['column2']);
    }

    public function testInsertLiteral(): void
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
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $rows[0]['column3']);
        $this->assertEquals('2024-01-01 00:00:00', $rows[1]['column3']);
        $this->assertEquals('2025-01-01 00:00:00', $rows[2]['column3']);
    }

    public function testBulkInsertDataEnum(): void
    {
        $table = new Table('table1', [], $this->adapter);
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

    public function testNullWithoutDefaultValue(): void
    {
        // construct table with default/null combinations
        $table = new Table('table1', [], $this->adapter);
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

    public function testDumpCreateTable(): void
    {
        $this->adapter->setOptions($this->adapter->getOptions() + ['dryrun' => true]);
        $table = new Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE "table1" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "column1" VARCHAR NOT NULL, "column2" INTEGER, "column3" VARCHAR DEFAULT 'test');
OUTPUT;
        $actualOutput = implode("\n", $this->out->messages());
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create table query to the output');
    }

    /**
     * Creates the table "table1".
     * Then sets phinx to dry run mode and inserts a record.
     * Asserts that phinx outputs the insert statement and doesn't insert a record.
     */
    public function testDumpInsert(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $this->adapter->setOptions($this->adapter->getOptions() + ['dryrun' => true]);
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
INSERT INTO "table1" ("string_col") VALUES ('test data');
INSERT INTO "table1" ("string_col") VALUES (null);
INSERT INTO "table1" ("int_col") VALUES (23);
OUTPUT;
        $actualOutput = implode("\n", $this->out->messages());
        $actualOutput = preg_replace("/\r\n|\r/", "\n", $actualOutput); // normalize line endings for Windows
        $this->assertStringContainsString($expectedOutput, $actualOutput, "Passing the --dry-run option doesn't dump the insert to the output");

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll();
        $this->assertEquals(0, $res[0][0]);
    }

    /**
     * Creates the table "table1".
     * Then sets phinx to dry run mode and inserts some records.
     * Asserts that phinx outputs the insert statement and doesn't insert any record.
     */
    public function testDumpBulkinsert(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $this->adapter->setOptions($this->adapter->getOptions() + ['dryrun' => true]);
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
INSERT INTO "table1" ("string_col", "int_col") VALUES ('test_data1', 23), (null, 42);
OUTPUT;
        $actualOutput = implode("\n", $this->out->messages());
        $this->assertStringContainsString($expectedOutput, $actualOutput, "Passing the --dry-run option doesn't dump the bulkinsert to the output");

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll();
        $this->assertEquals(0, $res[0][0]);
    }

    public function testDumpCreateTableAndThenInsert(): void
    {
        $this->adapter->setOptions($this->adapter->getOptions() + ['dryrun' => true]);
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
CREATE TABLE "table1" ("column1" VARCHAR NOT NULL, "column2" INTEGER, PRIMARY KEY ("column1"));
INSERT INTO "table1" ("column1", "column2") VALUES ('id1', 1);
OUTPUT;
        $actualOutput = implode("\n", $this->out->messages());
        $actualOutput = preg_replace("/\r\n|\r/", "\n", $actualOutput); // normalize line endings for Windows
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create and then insert table queries to the output');
    }

    /**
     * Tests interaction with the query builder
     */
    public function testQueryBuilder(): void
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

        $this->assertEquals(2, $stm->rowCount());

        $builder = $this->adapter->getSelectBuilder();
        $stm = $builder
            ->select('*')
            ->from('table1')
            ->where(['int_col >=' => 2])
            ->execute();

        $this->assertEquals(0, $stm->rowCount());
        $this->assertEquals(
            ['id' => 2, 'string_col' => 'value2', 'int_col' => '2'],
            $stm->fetch('assoc'),
        );

        $builder = $this->adapter->getDeleteBuilder();
        $stm = $builder
            ->delete('table1')
            ->where(['int_col <' => 2])
            ->execute();

        $this->assertEquals(1, $stm->rowCount());
    }

    public function testQueryWithParams(): void
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

    /**
     * Tests adding more than one column to a table
     * that already exists due to adapters having different add column instructions
     */
    public function testAlterTableColumnAdd(): void
    {
        $table = new Table('table1', [], $this->adapter);
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
            ['name' => 'created', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'null' => false],
            ['name' => 'updated', 'type' => 'timestamp', 'default' => null, 'null' => true],
        ];

        $this->assertEquals(count($expected), count($columns));

        $columnCount = count($columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $this->assertSame($expected[$i]['name'], $columns[$i]->getName(), 'Wrong name for ' . $expected[$i]['name']);
            $this->assertSame($expected[$i]['type'], $columns[$i]->getType(), 'Wrong type for ' . $expected[$i]['name']);
            $this->assertSame($expected[$i]['default'], $columns[$i]->getDefault() instanceof Literal ? (string)$columns[$i]->getDefault() : $columns[$i]->getDefault(), 'Wrong default for ' . $expected[$i]['name']);
            $this->assertSame($expected[$i]['null'], $columns[$i]->getNull(), 'Wrong null for ' . $expected[$i]['name']);
        }
    }

    public function testAlterTableWithConstraints(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->create();

        $table2 = new Table('table2', [], $this->adapter);
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
            $this->assertSame($expected[$i]['name'], $columns[$i]->getName(), 'Wrong name for ' . $expected[$i]['name']);
            $this->assertSame($expected[$i]['type'], $columns[$i]->getType(), 'Wrong type for ' . $expected[$i]['name']);
            $this->assertSame($expected[$i]['default'], $columns[$i]->getDefault() instanceof Literal ? (string)$columns[$i]->getDefault() : $columns[$i]->getDefault(), 'Wrong default for ' . $expected[$i]['name']);
            $this->assertSame($expected[$i]['null'], $columns[$i]->getNull(), 'Wrong null for ' . $expected[$i]['name']);
        }
    }

    /**
     * Tests that operations that trigger implicit table drops will not cause
     * a foreign key constraint violation error.
     */
    public function testAlterTableDoesNotViolateRestrictedForeignKeyConstraint(): void
    {
        $this->adapter->execute('PRAGMA foreign_keys = ON');

        $articlesTable = new Table('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $this->adapter);
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
    public function testAlterTableDoesViolateForeignKeyConstraintOnTargetTableChange(): void
    {
        $articlesTable = new Table('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $this->adapter);
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

    /**
     * Tests that foreign key constraint violations introduced around the table
     * alteration process (being it implicitly by the process itself or by the user)
     * will trigger an error accordingly.
     */
    public function testAlterTableDoesViolateForeignKeyConstraintOnSourceTableChange(): void
    {
        $adapter = $this
            ->getMockBuilder(SqliteAdapter::class)
            ->setConstructorArgs([$this->config, $this->io])
            ->onlyMethods(['query'])
            ->getMock();

        $adapterReflection = new ReflectionObject($adapter);
        $queryReflection = $adapterReflection->getParentClass()->getMethod('query');

        $adapter
            ->expects($this->atLeastOnce())
            ->method('query')
            ->willReturnCallback(function (string $sql, array $params = []) use ($adapter, $queryReflection) {
                if ($sql === 'PRAGMA foreign_key_check("comments")') {
                    $adapter->execute('PRAGMA foreign_keys = OFF');
                    $adapter->execute('DELETE FROM articles');
                    $adapter->execute('PRAGMA foreign_keys = ON');
                }

                return $queryReflection->invoke($adapter, $sql, $params);
            });

        $articlesTable = new Table('articles', [], $adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $adapter);
        $commentsTable
            ->addColumn('article_id', 'integer')
            ->addForeignKey('article_id', 'articles', 'id', [
                'update' => ForeignKey::RESTRICT,
                'delete' => ForeignKey::RESTRICT,
            ])
            ->insert(['id' => 1, 'article_id' => 1])
            ->save();

        $this->assertTrue($adapter->hasForeignKey('comments', ['article_id']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integrity constraint violation: FOREIGN KEY constraint on `comments` failed.');

        $commentsTable
            ->addColumn('new_column', 'integer')
            ->update();
    }

    /**
     * Tests that the adapter's foreign key validation does not apply when
     * the `foreign_keys` pragma is set to `OFF`.
     */
    public function testAlterTableForeignKeyConstraintValidationNotRunningWithDisabledForeignKeys(): void
    {
        $articlesTable = new Table('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $this->adapter);
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

        $noException = false;
        try {
            $articlesTable
                ->addColumn('new_column1', 'integer')
                ->update();

            $noException = true;
        } finally {
            $this->assertTrue($noException);
        }

        $this->adapter->execute('PRAGMA foreign_keys = ON');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integrity constraint violation: FOREIGN KEY constraint on `comments` failed.');

        $articlesTable
            ->addColumn('new_column2', 'integer')
            ->update();
    }

    public function testLiteralSupport(): void
    {
        $createQuery = <<<'INPUT'
CREATE TABLE `test` (`real_col` DECIMAL)
INPUT;
        $this->adapter->execute($createQuery);
        $table = new Table('test', [], $this->adapter);
        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertEquals(Literal::from('decimal'), array_pop($columns)->getType());
    }

    #[DataProvider('provideTableNamesForPresenceCheck')]
    public function testHasTable(string $createName, string $tableName, bool $exp): void
    {
        // Test case for issue #1535
        $conn = $this->adapter->getConnection();
        $conn->execute("ATTACH DATABASE ':memory:' as etc");
        $conn->execute('ATTACH DATABASE \':memory:\' as "main.db"');
        $conn->execute(sprintf('DROP TABLE IF EXISTS %s', $createName));
        $this->assertFalse($this->adapter->hasTable($tableName), sprintf('Adapter claims table %s exists when it does not', $tableName));
        $conn->execute(sprintf('CREATE TABLE %s (a text)', $createName));
        if ($exp) {
            $this->assertTrue($this->adapter->hasTable($tableName), sprintf('Adapter claims table %s does not exist when it does', $tableName));
        } else {
            $this->assertFalse($this->adapter->hasTable($tableName), sprintf('Adapter claims table %s exists when it does not', $tableName));
        }
    }

    public static function provideTableNamesForPresenceCheck(): array
    {
        return [
            'Ordinary table' => ['t', 't', true],
            'Ordinary table with schema' => ['t', 'main.t', true],
            'Temporary table' => ['temp.t', 't', true],
            'Temporary table with schema' => ['temp.t', 'temp.t', true],
            'Attached table' => ['etc.t', 't', true],
            'Attached table with schema' => ['etc.t', 'etc.t', true],
            'Wrong schema 1' => ['t', 'etc.t', false],
            'Wrong schema 2' => ['t', 'temp.t', false],
            'Missing schema' => ['t', 'not_attached.t', false],
            'Malicious table' => ['"\'"', "'", true],
            'Malicious missing table' => ['t', "'", false],
            'Table name case 1' => ['t', 'T', true],
            'Table name case 2' => ['T', 't', true],
            'Schema name case 1' => ['main.t', 'MAIN.t', true],
            'Schema name case 2' => ['MAIN.t', 'main.t', true],
            'Schema name case 3' => ['temp.t', 'TEMP.t', true],
            'Schema name case 4' => ['TEMP.t', 'temp.t', true],
            'Schema name case 5' => ['etc.t', 'ETC.t', true],
            'Schema name case 6' => ['ETC.t', 'etc.t', true],
            'PHP zero string 1' => ['"0"', '0', true],
            'PHP zero string 2' => ['"0"', '0e2', false],
            'PHP zero string 3' => ['"0e2"', '0', false],
        ];
    }

    /**
     * Test that hasTable() returns false after a table is dropped via execute().
     *
     * This verifies that hasTable() always checks the database rather than
     * relying on an internal cache that could become stale when raw SQL is used.
     */
    public function testHasTableAfterExecuteDrop(): void
    {
        // Create table via API
        $table = new Table('cache_test', [], $this->adapter);
        $table->addColumn('name', 'string')
              ->save();

        $this->assertTrue($this->adapter->hasTable('cache_test'));

        // Drop via execute() - hasTable() must still return false
        $this->adapter->execute('DROP TABLE "cache_test"');

        $this->assertFalse($this->adapter->hasTable('cache_test'));
    }

    #[DataProvider('provideIndexColumnsToCheck')]
    public function testHasIndex(string $tableDef, string|array $cols, bool $exp): void
    {
        $conn = $this->adapter->getConnection();
        if (str_contains($tableDef, ';')) {
            $queries = explode(';', $tableDef);
            foreach ($queries as $query) {
                $stmt = $conn->execute($query);
                $stmt->closeCursor();
            }
        } else {
            $stmt = $conn->execute($tableDef);
            $stmt->closeCursor();
        }

        $this->assertEquals($exp, $this->adapter->hasIndex('t', $cols));
    }

    public static function provideIndexColumnsToCheck(): array
    {
        return [
            ['create table t(a text)', 'a', false],
            ['create table t(a text); create index test on t(a)', 'a', true],
            ['create table t(a text unique)', 'a', true],
            ['create table t(a text primary key)', 'a', true],
            ['create table t(a text unique, b text unique)', ['a', 'b'], false],
            ['create table t(a text, b text, unique(a,b))', ['a', 'b'], true],
            ['create table t(a text, b text); create index test on t(a,b)', ['a', 'b'], true],
            ['create table t(a text, b text); create index test on t(a,b)', ['b', 'a'], false],
            ['create table t(a text, b text); create index test on t(a,b)', ['a'], false],
            ['create table t(a text, b text); create index test on t(a)', ['a', 'b'], false],
            ['create table t(a text, b text); create index test on t(a,b)', ['A', 'B'], false],
            ['create table t(a text, b text); create index test on t(a,b)', ['a', 'b'], true],
            ['create table t("A" text, "B" text); create index test on t("A","B")', ['A', 'B'], true],
            ['create table not_t(a text, b text, unique(a,b))', ['a', 'b'], false], // test checks table t which does not exist
            ['create table t(a text, b text); create index test on t(a)', ['a', 'a'], false],
            ['create table t(a text unique); create temp table t(a text)', 'a', false],
        ];
    }

    #[DataProvider('provideIndexNamesToCheck')]
    public function testHasIndexByName(string $tableDef, string $index, bool $exp): void
    {
        $conn = $this->adapter->getConnection();
        if (str_contains($tableDef, ';')) {
            $queries = explode(';', $tableDef);
            foreach ($queries as $query) {
                $stmt = $conn->execute($query);
                $stmt->closeCursor();
            }
        } else {
            $stmt = $conn->execute($tableDef);
            $stmt->closeCursor();
        }
        $this->assertEquals($exp, $this->adapter->hasIndexByName('t', $index));
    }

    public static function provideIndexNamesToCheck(): array
    {
        return [
            ['create table t(a text)', 'test', false],
            ['create table t(a text); create index test on t(a)', 'test', true],
            ['create table t(a text); create index test on t(a)', 'TEST', false],
            ['create table t(a text); create index "TEST" on t(a)', 'test', false],
            ['create table t(a text); create index "TEST" on t(a)', 'TEST', true],
            ['create table t(a text unique)', 'sqlite_autoindex_t_1', true],
            ['create table t(a text primary key)', 'sqlite_autoindex_t_1', true],
            ['create table not_t(a text); create index test on not_t(a)', 'test', false], // test checks table t which does not exist
            ['create table t(a text unique); create temp table t(a text)', 'sqlite_autoindex_t_1', false],
        ];
    }

    #[DataProvider('providePrimaryKeysToCheck')]
    public function testHasPrimaryKey(string $tableDef, string|array $key, bool $exp): void
    {
        $this->assertFalse($this->adapter->hasTable('t'), 'Dirty test fixture');
        $conn = $this->adapter->getConnection();
        if (str_contains($tableDef, ';')) {
            $queries = explode(';', $tableDef);
            foreach ($queries as $query) {
                $stmt = $conn->execute($query);
                $stmt->closeCursor();
            }
        } else {
            $stmt = $conn->execute($tableDef);
            $stmt->closeCursor();
        }
        $this->assertSame($exp, $this->adapter->hasPrimaryKey('t', $key));
    }

    public static function providePrimaryKeysToCheck(): array
    {
        return [
            ['create table t(a integer)', 'a', false],
            ['create table t(a integer)', [], true],
            ['create table t(a integer primary key)', 'a', true],
            ['create table t(a integer primary key)', [], false],
            ['create table t(a integer PRIMARY KEY)', 'a', true],
            ['create table t(`a` integer PRIMARY KEY)', 'a', true],
            ['create table t("a" integer PRIMARY KEY)', 'a', true],
            ['create table t([a] integer PRIMARY KEY)', 'a', true],
            ['create table t(`a` integer PRIMARY KEY)', 'a', true],
            ["create table t('a' integer PRIMARY KEY)", 'a', true],
            ['create table t(`a.a` integer PRIMARY KEY)', 'a.a', true],
            ['create table t(a integer primary key)', ['a'], true],
            ['create table t(a integer primary key)', ['a', 'b'], false],
            ['create table t(a integer, primary key(a))', 'a', true],
            ['create table t(a integer, primary key("a"))', 'a', true],
            ['create table t(a integer, primary key([a]))', 'a', true],
            ['create table t(a integer, primary key(`a`))', 'a', true],
            ['create table t(a integer, b integer primary key)', 'a', false],
            ['create table t(a integer, b text primary key)', 'b', true],
            ['create table t(a integer, b integer default 2112 primary key)', ['a'], false],
            ['create table t(a integer, b integer primary key)', ['b'], true],
            ['create table t(a integer, b integer primary key)', ['b', 'b'], true], // duplicate column is collapsed
            ['create table t(a integer, b integer, primary key(a,b))', ['b', 'a'], true],
            ['create table t(a integer, b integer, primary key(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, primary key(a,b))', 'a', false],
            ['create table t(a integer, b integer, primary key(a,b))', ['a'], false],
            ['create table t(a integer, b integer, primary key(a,b))', ['a', 'b', 'c'], false],
            ['create table t(a integer, b integer, primary key(a,b))', ['a', 'B'], true],
            ['create table t(a integer, "B" integer, primary key(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, constraint t_pk primary key(a,b))', ['a', 'b'], true],
            ['create table t(a integer); create temp table t(a integer primary key)', 'a', true],
            ['create temp table t(a integer primary key)', 'a', true],
            ['create table t("0" integer primary key)', ['0'], true],
            ['create table t("0" integer primary key)', ['0e0'], false],
            ['create table t("0e0" integer primary key)', ['0'], false],
            ['create table not_t(a integer)', 'a', false], // test checks table t which does not exist
        ];
    }

    public function testHasNamedPrimaryKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->adapter->hasPrimaryKey('t', [], 'named_constraint');
    }

    #[DataProvider('provideForeignKeysToCheck')]
    public function testHasForeignKey(string $tableDef, string|array $key, bool $exp): void
    {
        $conn = $this->adapter->getConnection();
        $conn->execute('CREATE TABLE other(a integer, b integer, c integer)');
        if (str_contains($tableDef, ';')) {
            $queries = explode(';', $tableDef);
            foreach ($queries as $query) {
                $stmt = $conn->execute($query);
                $stmt->closeCursor();
            }
        } else {
            $stmt = $conn->execute($tableDef);
            $stmt->closeCursor();
        }

        $this->assertSame($exp, $this->adapter->hasForeignKey('t', $key));
    }

    public static function provideForeignKeysToCheck(): array
    {
        return [
            ['create table t(a integer)', 'a', false],
            ['create table t(a integer)', [], false],
            ['create table t(a integer primary key)', 'a', false],
            ['create table t(a integer references other(a))', 'a', true],
            ['create table t(a integer references other(b))', 'a', true],
            ['create table t(a integer references other(b))', ['a'], true],
            ['create table t(a integer references other(b))', ['a', 'a'], false],
            ['create table t(a integer, foreign key(a) references other(a))', 'a', true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', 'a', false],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['b', 'a'], false],
            ['create table t(a integer, "B" integer, foreign key(a,"B") references other(a,b))', ['a', 'B'], true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['a', 'B'], false],
            ['create table t(a integer, b integer, c integer, foreign key(a,b,c) references other(a,b,c))', ['a', 'b'], false],
            ['create table t(a integer, foreign key(a) references other(a))', ['a', 'b'], false],
            ['create table t(a integer references other(a), b integer references other(b))', ['a', 'b'], false],
            ['create table t(a integer references other(a), b integer references other(b))', ['a', 'b'], false],
            ['create table t(a integer); create temp table t(a integer references other(a))', ['a'], true],
            ['create temp table t(a integer references other(a))', ['a'], true],
            ['create table t("0" integer references other(a))', '0', true],
            ['create table t("0" integer references other(a))', '0e0', false],
            ['create table t("0e0" integer references other(a))', '0', false],
        ];
    }

    #[DataProvider('hasNamedForeignKeyProvider')]
    public function testHasNamedForeignKey(string $keySql, ?string $keyName, array $columns, bool $expected): void
    {
        $refTable = new Table('tbl_parent_1', [], $this->adapter);
        $refTable->addColumn('column', 'string')->create();

        $refTable = new Table('tbl_parent_2', [], $this->adapter);
        $refTable->create();

        $refTable = new Table('tbl_parent_3', [
            'id' => false,
            'primary_key' => ['id', 'column'],
        ], $this->adapter);
        $refTable->addColumn('id', 'integer')->addColumn('column', 'string')->create();

        // use raw sql instead of table builder so that we can have check constraints
        $this->adapter->execute("
        CREATE TABLE `tbl_child` (
            `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `column` VARCHAR NOT NULL, `parent_1_id` INTEGER NOT NULL,
            `parent_2_id` INTEGER NOT NULL,
            `parent_3_id` INTEGER NOT NULL,
            {$keySql}
        )");

        $this->assertSame($expected, $this->adapter->hasForeignKey('tbl_child', $columns, $keyName));
    }

    /**
     * @return array
     */
    public static function hasNamedForeignKeyProvider(): array
    {
        return [
            // key sql, expected name, columns, expected presence
            [
                'CONSTRAINT `fk_parent_1_id` FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)',
                'fk_parent_1_id',
                [],
                true,
            ],
            [
                'CONSTRAINT [fk_[_brackets] FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)',
                'fk_[_brackets',
                [],
                true,
            ],
            [
                'CONSTRAINT `fk_``_ticks` FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)',
                'fk_`_ticks',
                [],
                true,
            ],
            [
                'CONSTRAINT "fk_""_double_quotes" FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)',
                'fk_"_double_quotes',
                [],
                true,
            ],
            [
                "CONSTRAINT 'fk_''_single_quotes' FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)",
                "fk_'_single_quotes",
                [],
                true,
            ],
            [
                'CONSTRAINT fk_no_quotes FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)',
                'fk_no_quotes',
                [],
                true,
            ],
            [
                'CONSTRAINT`fk_no_space`FOREIGN KEY(`parent_1_id`)REFERENCES`tbl_parent_1`(`id`)',
                'fk_no_space',
                [],
                true,
            ],
            [
                'constraint
                `fk_lots_of_space`    FOReign		KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)',
                'fk_lots_of_space',
                [],
                true,
            ],
            [
                'FOREIGN KEY (`parent_2_id`) REFERENCES `tbl_parent_2` (`id`)',
                null,
                ['parent_2_id'],
                true,
            ],
            [
                'CONSTRAINT `fk_parent_1_id` FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`)',
                null,
                ['parent_1_id'],
                true,
            ],
            [
                'CONSTRAINT `fk_composite_key` FOREIGN KEY (`parent_3_id`,`column`) REFERENCES `tbl_parent_3` (`id`,`column`)',
                null,
                ['parent_3_id', 'column'],
                true,
            ],
            // Should not find check constraints
            [
                "CONSTRAINT `check_constraint_1` CHECK (column<>'world')",
                'check_constraint_1',
                [],
                false,
            ],
        ];
    }

    #[DataProvider('provideColumnTypesForValidation')]
    public function testIsValidColumnType(string $phinxType, bool $exp): void
    {
        $col = (new Column())->setType($phinxType);
        $this->assertSame($exp, $this->adapter->isValidColumnType($col));
    }

    public static function provideColumnTypesForValidation(): array
    {
        return [
            [SqliteAdapter::TYPE_BIGINTEGER, true],
            [SqliteAdapter::TYPE_BINARY, true],
            [SqliteAdapter::TYPE_BOOLEAN, true],
            [SqliteAdapter::TYPE_CHAR, true],
            [SqliteAdapter::TYPE_DATE, true],
            [SqliteAdapter::TYPE_DATETIME, true],
            [SqliteAdapter::TYPE_FLOAT, true],
            [SqliteAdapter::TYPE_INTEGER, true],
            [SqliteAdapter::TYPE_JSON, true],
            [SqliteAdapter::TYPE_SMALLINTEGER, true],
            [SqliteAdapter::TYPE_STRING, true],
            [SqliteAdapter::TYPE_TEXT, true],
            [SqliteAdapter::TYPE_TIME, true],
            [SqliteAdapter::TYPE_UUID, true],
            [SqliteAdapter::TYPE_TIMESTAMP, true],
            [SqliteAdapter::TYPE_CIDR, false],
            [SqliteAdapter::TYPE_CITEXT, false],
            [SqliteAdapter::TYPE_DECIMAL, true],
            [SqliteAdapter::TYPE_GEOMETRY, false],
            [SqliteAdapter::TYPE_INET, false],
            [SqliteAdapter::TYPE_INTERVAL, false],
            [SqliteAdapter::TYPE_LINESTRING, false],
            [SqliteAdapter::TYPE_MACADDR, false],
            [SqliteAdapter::TYPE_POINT, false],
            [SqliteAdapter::TYPE_POLYGON, false],
            ['someType', false],
        ];
    }

    #[DataProvider('provideDatabaseVersionStrings')]
    public function testDatabaseVersionAtLeast(string $ver, bool $exp): void
    {
        $this->assertSame($exp, $this->adapter->databaseVersionAtLeast($ver));
    }

    public static function provideDatabaseVersionStrings(): array
    {
        return [
            ['2', true],
            ['3', true],
            ['4', false],
            ['3.0', true],
            ['3.0.0.0.0.0', true],
            ['3.0.0.0.0.99999', true],
            ['3.9999', false],
        ];
    }

    #[DataProvider('provideColumnNamesToCheck')]
    public function testHasColumn(string $tableDef, string $col, bool $exp): void
    {
        $conn = $this->adapter->getConnection();
        if (str_contains($tableDef, ';')) {
            $queries = explode(';', $tableDef);
            foreach ($queries as $query) {
                $stmt = $conn->execute($query);
                $stmt->closeCursor();
            }
        } else {
            $stmt = $conn->execute($tableDef);
            $stmt->closeCursor();
        }

        $this->assertEquals($exp, $this->adapter->hasColumn('t', $col));
    }

    public static function provideColumnNamesToCheck(): array
    {
        return [
            ['create table t(a text)', 'a', true],
            ['create table t(A text)', 'a', false],
            ['create table t(A text)', 'A', true],
            ['create table t("a" text)', 'a', true],
            ['create table t([a] text)', 'a', true],
            ["create table t('a' text)", 'a', true],
            ['create table t("A" text)', 'A', true],
            ['create table t(a text)', 'a', true],
            ['create table t(b text)', 'a', false],
            ['create table t(b text, a text)', 'a', true],
            ['create table t("0" text)', '0', true],
            ['create table t("0" text)', '0e0', false],
            ['create table t("0e0" text)', '0', false],
            ['create table t(b text); create temp table t(a text)', 'a', true],
            ['create table not_t(a text)', 'a', false],
        ];
    }

    public function testGetColumns(): void
    {
        $conn = $this->adapter->getConnection();
        $conn->execute('create table t(a integer, b text, c char(5), d integer(12,6), e integer not null, f integer null)');

        $exp = [
            ['name' => 'a', 'type' => 'integer', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'b', 'type' => 'text', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'c', 'type' => 'char', 'null' => true, 'limit' => 5],
            ['name' => 'd', 'type' => 'integer', 'null' => true, 'limit' => 12],
            ['name' => 'e', 'type' => 'integer', 'null' => false, 'limit' => null],
            ['name' => 'f', 'type' => 'integer', 'null' => true, 'limit' => null],
        ];
        $act = $this->adapter->getColumns('t');
        $this->assertCount(count($exp), $act);
        foreach ($exp as $index => $data) {
            $this->assertInstanceOf(Column::class, $act[$index]);
            foreach ($data as $key => $value) {
                $m = 'get' . ucfirst($key);
                $this->assertEquals($value, $act[$index]->$m(), sprintf("Parameter '%s' of column at index %s did not match expectations.", $key, $index));
            }
        }
    }

    #[DataProvider('provideIdentityCandidates')]
    public function testGetColumnsForIdentity(string $tableDef, ?string $exp): void
    {
        $conn = $this->adapter->getConnection();
        $conn->execute($tableDef);

        $cols = $this->adapter->getColumns('t');
        $act = [];
        foreach ($cols as $col) {
            if ($col->getIdentity()) {
                $act[] = $col->getName();
            }
        }
        $this->assertEquals((array)$exp, $act);
    }

    public static function provideIdentityCandidates(): array
    {
        return [
            ['create table t(a text)', null],
            ['create table t(a text primary key)', 'a'],
            ['create table t(a integer, b text, primary key(a,b))', null],
            ['create table t(a integer primary key desc)', 'a'],
            ['create table t(a integer primary key) without rowid', 'a'],
            ['create table t(a integer primary key)', 'a'],
            ['CREATE TABLE T(A INTEGER PRIMARY KEY)', 'A'],
            ['create table t(a integer, primary key(a))', 'a'],
        ];
    }

    #[DataProvider('provideDefaultValues')]
    public function testGetColumnsForDefaults(string $tableDef, string|Literal|int|float|Expression|null $exp): void
    {
        $conn = $this->adapter->getConnection();
        $conn->execute($tableDef);

        $act = $this->adapter->getColumns('t')[0]->getDefault();
        if (is_object($exp)) {
            $this->assertEquals($exp, $act);
        } else {
            $this->assertSame($exp, $act);
        }
    }

    public static function provideDefaultValues(): array
    {
        return [
            'Implicit null' => ['create table t(a integer)', null],
            'Explicit null LC' => ['create table t(a integer default null)', null],
            'Explicit null UC' => ['create table t(a integer default NULL)', null],
            'Explicit null MC' => ['create table t(a integer default nuLL)', null],
            'Extra parentheses' => ['create table t(a integer default ( ( null ) ))', null],
            'Comment 1' => ['create table t(a integer default ( /* this is perfectly fine */ null ))', null],
            'Comment 2' => ["create table t(a integer default ( /* this\nis\nperfectly\nfine */ null ))", null],
            'Line comment 1' => ["create table t(a integer default ( -- this is perfectly fine, too\n null ))", null],
            'Line comment 2' => ["create table t(a integer default ( -- this is perfectly fine, too\r\n null ))", null],
            'Current date LC' => ['create table t(a date default current_date)', 'CURRENT_DATE'],
            'Current date UC' => ['create table t(a date default CURRENT_DATE)', 'CURRENT_DATE'],
            'Current date MC' => ['create table t(a date default CURRENT_date)', 'CURRENT_DATE'],
            'Current time LC' => ['create table t(a time default current_time)', 'CURRENT_TIME'],
            'Current time UC' => ['create table t(a time default CURRENT_TIME)', 'CURRENT_TIME'],
            'Current time MC' => ['create table t(a time default CURRENT_time)', 'CURRENT_TIME'],
            'Current timestamp LC' => ['create table t(a datetime default current_timestamp)', 'CURRENT_TIMESTAMP'],
            'Current timestamp UC' => ['create table t(a datetime default CURRENT_TIMESTAMP)', 'CURRENT_TIMESTAMP'],
            'Current timestamp MC' => ['create table t(a datetime default CURRENT_timestamp)', 'CURRENT_TIMESTAMP'],
            'String 1' => ["create table t(a text default '')", Literal::from('')],
            'String 2' => ["create table t(a text default 'value!')", Literal::from('value!')],
            'String 3' => ["create table t(a text default 'O''Brien')", Literal::from("O'Brien")],
            'String 4' => ["create table t(a text default 'CURRENT_TIMESTAMP')", Literal::from('CURRENT_TIMESTAMP')],
            'String 5' => ["create table t(a text default 'current_timestamp')", Literal::from('current_timestamp')],
            'String 6' => ["create table t(a text default '' /* comment */)", Literal::from('')],
            'Hexadecimal LC' => ['create table t(a integer default 0xff)', 255],
            'Hexadecimal UC' => ['create table t(a integer default 0XFF)', 255],
            'Hexadecimal MC' => ['create table t(a integer default 0x1F)', 31],
            'Integer 1' => ['create table t(a integer default 1)', 1],
            'Integer 2' => ['create table t(a integer default -1)', -1],
            'Integer 3' => ['create table t(a integer default +1)', 1],
            'Integer 4' => ['create table t(a integer default 2112)', 2112],
            'Integer 5' => ['create table t(a integer default 002112)', 2112],
            'Integer boolean 1' => ['create table t(a boolean default 1)', 1],
            'Integer boolean 2' => ['create table t(a boolean default 0)', 0],
            'Integer boolean 3' => ['create table t(a boolean default -1)', 0],
            'Integer boolean 4' => ['create table t(a boolean default 2)', 0],
            'Float 1' => ['create table t(a float default 1.0)', 1.0],
            'Float 2' => ['create table t(a float default +1.0)', 1.0],
            'Float 3' => ['create table t(a float default -1.0)', -1.0],
            'Float 4' => ['create table t(a float default 1.)', 1.0],
            'Float 5' => ['create table t(a float default 0.1)', 0.1],
            'Float 6' => ['create table t(a float default .1)', 0.1],
            'Float 7' => ['create table t(a float default 1e0)', 1.0],
            'Float 8' => ['create table t(a float default 1e+0)', 1.0],
            'Float 9' => ['create table t(a float default 1e+1)', 10.0],
            'Float 10' => ['create table t(a float default 1e-1)', 0.1],
            'Float 11' => ['create table t(a float default 1E-1)', 0.1],
            'Blob literal 1' => ["create table t(a float default x'ff')", Expression::from("x'ff'")],
            'Blob literal 2' => ["create table t(a float default X'FF')", Expression::from("X'FF'")],
            'Arbitrary expression' => ['create table t(a float default ((2) + (2)))', Expression::from('(2) + (2)')],
            'Pathological case 1' => ["create table t(a float default ('/*' || '*/'))", Expression::from("/*' || '*/")],
        ];
    }

    #[DataProvider('provideBooleanDefaultValues')]
    public function testGetColumnsForBooleanDefaults(string $tableDef, int $exp): void
    {
        if (!$this->adapter->databaseVersionAtLeast('3.24')) {
            $this->markTestSkipped('SQLite 3.24.0 or later is required for this test.');
        }
        $conn = $this->adapter->getConnection();
        $conn->execute($tableDef);

        $act = $this->adapter->getColumns('t')[0]->getDefault();
        $this->assertSame($exp, $act);
    }

    public static function provideBooleanDefaultValues(): array
    {
        return [
            'True LC' => ['create table t(a boolean default true)', 1],
            'True UC' => ['create table t(a boolean default TRUE)', 1],
            'True MC' => ['create table t(a boolean default TRue)', 1],
            'False LC' => ['create table t(a boolean default false)', 0],
            'False UC' => ['create table t(a boolean default FALSE)', 0],
            'False MC' => ['create table t(a boolean default FALse)', 0],
        ];
    }

    #[DataProvider('provideTablesForTruncation')]
    public function testTruncateTable(string $tableDef, string $tableName, string $tableId): void
    {
        $conn = $this->adapter->getConnection();
        $conn->execute($tableDef);
        $conn->execute(sprintf('INSERT INTO %s default values', $tableId));
        $conn->execute(sprintf('INSERT INTO %s default values', $tableId));
        $conn->execute(sprintf('INSERT INTO %s default values', $tableId));
        $this->assertEquals(3, $conn->execute('select count(*) from ' . $tableId)->fetchColumn(0), 'Broken fixture: data were not inserted properly');
        $this->assertEquals(3, $conn->execute('select max(id) from ' . $tableId)->fetchColumn(0), 'Broken fixture: data were not inserted properly');
        $this->adapter->truncateTable($tableName);
        $this->assertEquals(0, $conn->execute('select count(*) from ' . $tableId)->fetchColumn(0), 'Table was not truncated');
        $conn->execute(sprintf('INSERT INTO %s default values', $tableId));
        $this->assertEquals(1, $conn->execute('select max(id) from ' . $tableId)->fetchColumn(0), 'Autoincrement was not reset');
        $conn->execute('DROP TABLE ' . $tableId);
    }

    /**
     * @return array
     */
    public static function provideTablesForTruncation(): array
    {
        return [
            ['create table t(id integer primary key)', 't', 't'],
            ['create table t(id integer primary key autoincrement)', 't', 't'],
            ['create temp table t(id integer primary key)', 't', 'temp.t'],
            ['create temp table t(id integer primary key autoincrement)', 't', 'temp.t'],
            ['create table t(id integer primary key)', 'main.t', 'main.t'],
            ['create table t(id integer primary key autoincrement)', 'main.t', 'main.t'],
            ['create temp table t(id integer primary key)', 'temp.t', 'temp.t'],
            ['create temp table t(id integer primary key autoincrement)', 'temp.t', 'temp.t'],
            ['create table T(id integer primary key)', 't', 't'],
            ['create table T(id integer primary key autoincrement)', 't', 't'],
            ['create table t(id integer primary key)', 'T', 't'],
            ['create table t(id integer primary key autoincrement)', 'T', 't'],
        ];
    }

    public function testForeignKeyReferenceCorrectAfterRenameColumn(): void
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToRename = 'columnToRename';
        $refTableRenamedColumn = 'renamedColumn';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRename, 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->renameColumn($refTableColumnToRename, $refTableRenamedColumn)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable('tmp_' . $refTable->getName()));
        $this->assertTrue($this->adapter->hasColumn($refTable->getName(), $refTableRenamedColumn));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where "type" = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString(sprintf('REFERENCES "%s" ("id")', $refTable->getName()), $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangeColumn(): void
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToChange = 'columnToChange';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToChange, 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->changeColumn($refTableColumnToChange, 'text')->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable('tmp_' . $refTable->getName()));
        $this->assertEquals('text', $this->adapter->getColumns($refTable->getName())[1]->getType());

        $rows = $this->adapter->fetchAll('select * from sqlite_master where "type" = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString(sprintf('REFERENCES "%s" ("id")', $refTable->getName()), $sql);
    }

    public function testForeignKeyReferenceCorrectAfterRemoveColumn(): void
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToRemove = 'columnToRemove';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRemove, 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->removeColumn($refTableColumnToRemove)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable('tmp_' . $refTable->getName()));
        $this->assertFalse($this->adapter->hasColumn($refTable->getName(), $refTableColumnToRemove));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where "type" = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString(sprintf('REFERENCES "%s" ("id")', $refTable->getName()), $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangePrimaryKey(): void
    {
        $refTableColumnAdditionalId = 'additional_id';
        $refTableColumnId = 'ref_table_id';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnAdditionalId, 'integer')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable
            ->addIndex('id', ['unique' => true])
            ->changePrimaryKey($refTableColumnAdditionalId)
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable('tmp_' . $refTable->getName()));
        $this->assertTrue($this->adapter->getColumns($refTable->getName())[1]->getIdentity());

        $rows = $this->adapter->fetchAll('select * from sqlite_master where "type" = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString(sprintf('REFERENCES "%s" ("id")', $refTable->getName()), $sql);
    }

    public function testForeignKeyReferenceCorrectAfterDropForeignKey(): void
    {
        $refTableAdditionalColumnId = 'ref_table_additional_id';
        $refTableAdditional = new Table('ref_table_additional', [], $this->adapter);
        $refTableAdditional->save();

        $refTableColumnId = 'ref_table_id';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableAdditionalColumnId, 'integer');
        $refTable->addForeignKey($refTableAdditionalColumnId, $refTableAdditional->getName(), 'id');
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->dropForeignKey($refTableAdditionalColumnId)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable('tmp_' . $refTable->getName()));
        $this->assertFalse($this->adapter->hasForeignKey($refTable->getName(), [$refTableAdditionalColumnId]));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where "type" = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString(sprintf('REFERENCES "%s" ("id")', $refTable->getName()), $sql);
    }

    public function testPdoExceptionUpdateNonExistingTable(): void
    {
        $this->expectException(PDOException::class);
        $table = new Table('non_existing_table', [], $this->adapter);
        $table->addColumn('column', 'string')->update();
    }

    public function testAddCheckConstraint(): void
    {
        $table = new Table('check_table', [], $this->adapter);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
              ->create();

        $checkConstraint = new CheckConstraint('price_positive', 'price > 0');
        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);

        $this->assertTrue($this->adapter->hasCheckConstraint('check_table', 'price_positive'));
    }

    public function testAddCheckConstraintAutoGeneratesName(): void
    {
        $table = new Table('check_table_auto', [], $this->adapter);
        $table->addColumn('quantity', 'integer')
              ->create();

        $expression = 'quantity >= 0';
        // An empty name must trigger auto-generation, not produce an unnamed constraint.
        $checkConstraint = new CheckConstraint('', $expression);
        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);

        $expectedName = 'chk_' . substr(md5($expression), 0, 8);
        $this->assertTrue($this->adapter->hasCheckConstraint('check_table_auto', $expectedName));
    }

    public function testHasCheckConstraint(): void
    {
        $table = new Table('check_table3', [], $this->adapter);
        $table->addColumn('quantity', 'integer')
              ->create();

        $checkConstraint = new CheckConstraint('quantity_positive', 'quantity > 0');
        $this->assertFalse($this->adapter->hasCheckConstraint('check_table3', 'quantity_positive'));

        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);

        $this->assertTrue($this->adapter->hasCheckConstraint('check_table3', 'quantity_positive'));
    }

    public function testDropCheckConstraint(): void
    {
        $table = new Table('check_table4', [], $this->adapter);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
              ->create();

        $checkConstraint = new CheckConstraint('price_check', 'price BETWEEN 0 AND 1000');
        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);
        $this->assertTrue($this->adapter->hasCheckConstraint('check_table4', 'price_check'));

        $this->adapter->dropCheckConstraint('check_table4', 'price_check');
        $this->assertFalse($this->adapter->hasCheckConstraint('check_table4', 'price_check'));
    }

    public function testCheckConstraintWithComplexExpression(): void
    {
        $table = new Table('check_table5', [], $this->adapter);
        $table->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('status', 'string', ['limit' => 20])
              ->create();

        $checkConstraint = new CheckConstraint(
            'status_valid',
            "status IN ('active', 'inactive', 'pending')",
        );
        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);
        $this->assertTrue($this->adapter->hasCheckConstraint('check_table5', 'status_valid'));

        // Verify the constraint is actually enforced
        $quotedTableName = $this->adapter->getConnection()->getDriver()->quoteIdentifier('check_table5');
        $this->expectException(PDOException::class);
        $this->adapter->execute(sprintf("INSERT INTO %s (email, status) VALUES ('test@example.com', 'invalid')", $quotedTableName));
    }

    public function testInsertOrSkipWithDuplicates(): void
    {
        $table = new Table('users', [], $this->adapter);
        $table->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('name', 'string')
            ->addIndex('email', ['unique' => true])
            ->create();

        // First insert
        $table->insertOrSkip([
            ['email' => 'test@example.com', 'name' => 'John'],
        ])->save();

        // Duplicate - should be skipped
        $table->insertOrSkip([
            ['email' => 'test@example.com', 'name' => 'Jane'],
        ])->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM users');
        $this->assertCount(1, $rows);
        $this->assertEquals('John', $rows[0]['name']);
    }

    public function testInsertModeResetsAfterInsertOrSkip(): void
    {
        $table = new Table('users', [], $this->adapter);
        $table->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('name', 'string')
            ->addIndex('email', ['unique' => true])
            ->create();

        // First insert with insertOrSkip
        $table->insertOrSkip([
            ['email' => 'test@example.com', 'name' => 'John'],
        ])->save();

        // Now use regular insert with duplicate - should throw exception
        $this->expectException(PDOException::class);
        $table->insert([
            ['email' => 'test@example.com', 'name' => 'Jane'],
        ])->save();
    }

    public function testBulkinsertOrSkipWithDuplicates(): void
    {
        $table = new Table('products', [], $this->adapter);
        $table->addColumn('sku', 'string', ['limit' => 50])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addIndex('sku', ['unique' => true])
            ->create();

        // First bulk insert
        $table->insertOrSkip([
            ['sku' => 'ABC123', 'price' => 10.50],
            ['sku' => 'DEF456', 'price' => 20.00],
        ])->save();

        // Mix of new and duplicate - duplicates should be skipped
        $table->insertOrSkip([
            ['sku' => 'ABC123', 'price' => 99.99], // Duplicate
            ['sku' => 'GHI789', 'price' => 30.00], // New
        ])->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM products ORDER BY sku');
        $this->assertCount(3, $rows);
        $this->assertEquals('10.50', $rows[0]['price']); // Original price preserved
        $this->assertEquals('20.00', $rows[1]['price']);
        $this->assertEquals('30.00', $rows[2]['price']);
    }

    public function testInsertOrSkipWithoutDuplicates(): void
    {
        $table = new Table('categories', [], $this->adapter);
        $table->addColumn('name', 'string')
            ->create();

        // Should work like normal insert
        $table->insertOrSkip([
            ['name' => 'Category 1'],
            ['name' => 'Category 2'],
        ])->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM categories');
        $this->assertCount(2, $rows);
    }

    public function testInsertOrUpdateWithDuplicates(): void
    {
        $table = new Table('currencies', [], $this->adapter);
        $table->addColumn('code', 'string', ['limit' => 3])
            ->addColumn('rate', 'decimal', ['precision' => 10, 'scale' => 4])
            ->addIndex('code', ['unique' => true])
            ->create();

        // First insert
        $table->insertOrUpdate([
            ['code' => 'USD', 'rate' => 1.0000],
            ['code' => 'EUR', 'rate' => 0.9000],
        ], ['rate'], ['code'])->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM currencies ORDER BY code');
        $this->assertCount(2, $rows);
        $this->assertEquals('0.9000', $rows[0]['rate']); // EUR
        $this->assertEquals('1.0000', $rows[1]['rate']); // USD

        // Update rates - should update existing rows
        $table->insertOrUpdate([
            ['code' => 'USD', 'rate' => 1.0500],
            ['code' => 'EUR', 'rate' => 0.9234],
            ['code' => 'GBP', 'rate' => 0.7800], // New row
        ], ['rate'], ['code'])->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM currencies ORDER BY code');
        $this->assertCount(3, $rows);
        $this->assertEquals('0.9234', $rows[0]['rate']); // EUR updated
        $this->assertEquals('0.7800', $rows[1]['rate']); // GBP new
        $this->assertEquals('1.0500', $rows[2]['rate']); // USD updated
    }

    public function testInsertOrUpdateWithMultipleUpdateColumns(): void
    {
        $table = new Table('products', [], $this->adapter);
        $table->addColumn('sku', 'string', ['limit' => 50])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('stock', 'integer')
            ->addIndex('sku', ['unique' => true])
            ->create();

        // First insert
        $table->insertOrUpdate([
            ['sku' => 'ABC123', 'price' => 10.00, 'stock' => 100],
        ], ['price', 'stock'], ['sku'])->save();

        // Update both price and stock
        $table->insertOrUpdate([
            ['sku' => 'ABC123', 'price' => 15.00, 'stock' => 50],
        ], ['price', 'stock'], ['sku'])->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM products');
        $this->assertCount(1, $rows);
        $this->assertEquals('15.00', $rows[0]['price']);
        $this->assertEquals(50, $rows[0]['stock']);
    }

    public function testInsertOrUpdateModeResetsAfterSave(): void
    {
        $table = new Table('items', [], $this->adapter);
        $table->addColumn('code', 'string', ['limit' => 10])
            ->addColumn('name', 'string')
            ->addIndex('code', ['unique' => true])
            ->create();

        // Use insertOrUpdate
        $table->insertOrUpdate([
            ['code' => 'ITEM1', 'name' => 'Item One'],
        ], ['name'], ['code'])->save();

        // Now use regular insert with duplicate - should throw exception
        $this->expectException(PDOException::class);
        $table->insert([
            ['code' => 'ITEM1', 'name' => 'Different Name'],
        ])->save();
    }

    public function testInsertOrUpdateRequiresConflictColumns(): void
    {
        $table = new Table('currencies', [], $this->adapter);
        $table->addColumn('code', 'string', ['limit' => 3])
            ->addColumn('rate', 'decimal', ['precision' => 10, 'scale' => 4])
            ->addIndex('code', ['unique' => true])
            ->create();

        // SQLite requires conflictColumns for insertOrUpdate
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SQLite requires the $conflictColumns parameter');
        $table->insertOrUpdate([
            ['code' => 'USD', 'rate' => 1.0000],
        ], ['rate'], [])->save();
    }
}
