<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;
use Migrations\Db\Adapter\MysqlAdapter;
use Migrations\Db\Literal;
use Migrations\Db\Table;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class MysqlAdapterTest extends TestCase
{
    /**
     * @var \Migrations\Db\Adapter\MysqlAdapter
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
        $config = ConnectionManager::getConfig('test');
        if ($config['scheme'] !== 'mysql') {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        // Emulate the results of Util::parseDsn()
        $this->config = [
            'adapter' => 'mysql',
            'connection' => ConnectionManager::get('test'),
            'database' => $config['database'],
        ];
        $this->adapter = new MysqlAdapter($this->config, $this->getConsoleIo());

        // ensure the database is empty for each test
        $this->adapter->dropDatabase($this->config['database']);
        $this->adapter->createDatabase($this->config['database'], ['charset' => 'utf8mb4']);

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

    private function getDefaultCollation(): string
    {
        return $this->usingMariaDbWithUuid() ?
            'utf8mb4_general_ci' :
            'utf8mb4_0900_ai_ci';
    }

    private function usingMysql8(): bool
    {
        $version = $this->adapter->getConnection()->getDriver()->version();

        return version_compare($version, '8.0.0', '>=')
            && version_compare($version, '10.0.0', '<');
    }

    private function usingMariaDb(): bool
    {
        $version = $this->adapter->getConnection()->getDriver()->version();

        return str_contains($version, 'MariaDB') || version_compare($version, '10.0.0', '>=');
    }

    private function usingMariaDbWithUuid(): bool
    {
        $version = $this->adapter->getConnection()->getDriver()->version();

        return version_compare($version, '10.7.0', '>=');
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

    public function testDatabaseNameWithEscapedCharacter()
    {
        $this->adapter->dropDatabase($this->config['database'] . '-test');
        $this->adapter->createDatabase($this->config['database'] . '-test', ['charset' => 'utf8mb4']);
        $this->assertTrue($this->adapter->hasDatabase($this->config['database'] . '-test'));
        $this->adapter->dropDatabase($this->config['database'] . '-test');
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('`test_table`', $this->adapter->quoteTableName('test_table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('`test_column`', $this->adapter->quoteColumnName('test_column'));
    }

    public function testHasTableUnderstandsSchemaNotation()
    {
        $this->assertTrue($this->adapter->hasTable('performance_schema.threads'), 'Failed asserting hasTable understands tables in another schema.');
        $this->assertFalse($this->adapter->hasTable('performance_schema.unknown_table'));
        $this->assertFalse($this->adapter->hasTable('unknown_schema.phinxlog'));
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

        $columns = $this->adapter->getColumns('ntable');
        $this->assertCount(3, $columns);
        $this->assertSame('id', $columns[0]->getName());
        $this->assertFalse($columns[0]->isSigned());
    }

    public function testCreateTableWithComment()
    {
        $tableComment = 'Table comment';
        $table = new Table('ntable', ['comment' => $tableComment], $this->adapter);
        $table->addColumn('realname', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='ntable'",
            $this->config['database'],
        ));
        $comment = $rows[0];

        $this->assertEquals($tableComment, $comment['TABLE_COMMENT'], 'Dont set table comment correctly');
    }

    public function testCreateTableWithForeignKeys()
    {
        $tag_table = new Table('ntable_tag', [], $this->adapter);
        $tag_table->addColumn('realname', 'string')
                  ->save();

        $table = new Table('ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('tag_id', 'integer', ['signed' => false])
              ->addForeignKey('tag_id', 'ntable_tag', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION'])
              ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA='%s' AND REFERENCED_TABLE_NAME='ntable_tag'",
            $this->config['database'],
        ));
        $foreignKey = $rows[0];

        $this->assertEquals($foreignKey['TABLE_NAME'], 'ntable');
        $this->assertEquals($foreignKey['COLUMN_NAME'], 'tag_id');
        $this->assertEquals($foreignKey['REFERENCED_TABLE_NAME'], 'ntable_tag');
        $this->assertEquals($foreignKey['REFERENCED_COLUMN_NAME'], 'id');
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
        $this->assertFalse($this->adapter->hasIndex('table1', ['USER_ID', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_id']));
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
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'uuid', ['null' => false])->save();
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
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
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'binaryuuid', ['null' => false])->save();
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsNativeUuid()
    {
        if (!$this->usingMariaDbWithUuid()) {
            $this->markTestSkipped('Database does not have a native uuid type');
        }

        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'nativeuuid', ['null' => false])->save();
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
        $table->addColumn('email', 'string', ['limit' => 191])
              ->addIndex('email', ['unique' => true])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithFullTextIndex()
    {
        $table = new Table('table1', ['engine' => 'MyISAM'], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['type' => 'fulltext'])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndex()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertTrue($this->adapter->hasIndexByName('table1', 'myemailindex'));
    }

    public function testCreateTableWithMyISAMEngine()
    {
        $table = new Table('ntable', ['engine' => 'MyISAM'], $this->adapter);
        $table->addColumn('realname', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $row = $this->adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'ntable'));
        $this->assertEquals('MyISAM', $row['Engine']);
    }

    public function testCreateTableAndInheritDefaultCollation()
    {
        $options = $this->config + [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
        $adapter = new MysqlAdapter($options, $this->io);

        $table = new Table('table_with_default_collation', [], $adapter);
        $table->addColumn('name', 'string')
              ->save();
        $this->assertTrue($adapter->hasTable('table_with_default_collation'));
        $row = $adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'table_with_default_collation'));
        $this->assertContains($row['Collation'], ['utf8mb4_general_ci', 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci']);
    }

    public function testCreateTableWithLatin1Collate()
    {
        $table = new Table('latin1_table', ['collation' => 'latin1_general_ci'], $this->adapter);
        $table->addColumn('name', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('latin1_table'));
        $row = $this->adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'latin1_table'));
        $this->assertEquals('latin1_general_ci', $row['Collation']);
    }

    public function testCreateTableWithSignedPK()
    {
        $table = new Table('ntable', ['signed' => true], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
        $column_definitions = $this->adapter->getColumns('ntable');
        foreach ($column_definitions as $column_definition) {
            if ($column_definition->getName() === 'id') {
                $this->assertTrue($column_definition->getSigned());
            }
        }
    }

    public function testCreateTableWithUnsignedPK()
    {
        $table = new Table('ntable', ['signed' => false], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
        $column_definitions = $this->adapter->getColumns('ntable');
        foreach ($column_definitions as $column_definition) {
            if ($column_definition->getName() === 'id') {
                $this->assertFalse($column_definition->getSigned());
            }
        }
    }

    public function testCreateTableWithUnsignedNamedPK()
    {
        $table = new Table('ntable', ['id' => 'named_id', 'signed' => false], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'named_id'));
        $column_definitions = $this->adapter->getColumns('ntable');
        foreach ($column_definitions as $column_definition) {
            if ($column_definition->getName() === 'named_id') {
                $this->assertFalse($column_definition->getSigned());
            }
        }
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableWithSetEnumTypes()
    {
        $table = new Table('enum_test', [], $this->adapter);
        $table->addColumn('status', 'enum', ['values' => ['pending', 'active', 'archived']])
              ->addColumn('kind', 'set', ['values' => ['a', 'b']])
              ->save();

        $this->assertTrue($this->adapter->hasTable('enum_test'));
        $this->assertTrue($this->adapter->hasColumn('enum_test', 'status'));
        $this->assertTrue($this->adapter->hasColumn('enum_test', 'kind'));
    }

    #[RunInSeparateProcess]
    public function testUnsignedPksFeatureFlag()
    {
        $this->adapter->connect();

        Configure::write('Migrations.unsigned_primary_keys', false);

        $table = new Table('table1', [], $this->adapter);
        $table->create();

        $columns = $this->adapter->getColumns('table1');
        $this->assertCount(1, $columns);
        $this->assertSame('id', $columns[0]->getName());
        $this->assertTrue($columns[0]->getSigned());
    }

    #[RunInSeparateProcess]
    public function testAddTimestampsFeatureFlag()
    {
        Configure::write('Migrations.add_timestamps_use_datetime', true);
        $this->adapter->connect();

        $table = new Table('table1', [], $this->adapter);
        $table->addTimestamps();
        $table->create();

        $columns = $this->adapter->getColumns('table1');

        $this->assertCount(3, $columns);
        $this->assertSame('id', $columns[0]->getName());

        $this->assertEquals('created', $columns[1]->getName());
        $this->assertEquals('datetime', $columns[1]->getType());
        $this->assertEquals('', $columns[1]->getUpdate());
        $this->assertFalse($columns[1]->isNull());
        $this->assertContains($columns[1]->getDefault(), ['CURRENT_TIMESTAMP', 'current_timestamp()']);

        $this->assertEquals('updated', $columns[2]->getName());
        $this->assertEquals('datetime', $columns[2]->getType());
        $this->assertContains($columns[2]->getUpdate(), ['CURRENT_TIMESTAMP', 'current_timestamp()']);
        $this->assertTrue($columns[2]->isNull());
        $this->assertNull($columns[2]->getDefault());
    }

    public function testCreateTableWithSchema()
    {
        $table = new Table($this->config['database'] . '.ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
    }

    public function testAddPrimarykey()
    {
        $table = new Table('table1', ['id' => false], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
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
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'integer')
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

    public function testAddComment()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment1')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT TABLE_COMMENT
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA='%s'
                        AND TABLE_NAME='%s'",
                $this->config['database'],
                'table1',
            ),
        );
        $this->assertEquals('comment1', $rows[0]['TABLE_COMMENT']);
    }

    public function testChangeComment()
    {
        $table = new Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment2')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT TABLE_COMMENT
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA='%s'
                        AND TABLE_NAME='%s'",
                $this->config['database'],
                'table1',
            ),
        );
        $this->assertEquals('comment2', $rows[0]['TABLE_COMMENT']);
    }

    public function testDropComment()
    {
        $table = new Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment(null)
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT TABLE_COMMENT
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA='%s'
                        AND TABLE_NAME='%s'",
                $this->config['database'],
                'table1',
            ),
        );
        $this->assertEquals('', $rows[0]['TABLE_COMMENT']);
    }

    public function testRenameTable()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));

        $table->rename('table2')->save();
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
        $table->addColumn('realname', 'string', ['after' => 'id'])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('realname', $rows[1]['Field']);
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('test', $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals('0', $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('', $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultBoolean()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_true', 'boolean', ['default' => true])
              ->addColumn('default_false', 'boolean', ['default' => false])
              ->addColumn('default_null', 'boolean', ['default' => null, 'null' => true])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('1', $rows[1]['Default']);
        $this->assertEquals('0', $rows[2]['Default']);
        $this->assertNull($rows[3]['Default']);
    }

    public function testAddColumnWithDefaultLiteral()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table
            ->addColumn('default_ts', 'timestamp', ['null' => false, 'default' => Literal::from('CURRENT_TIMESTAMP')])
            ->addColumn('char_default', 'string', ['null' => false, 'default' => Literal::from('"oh hi"')])
            ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        // MariaDB returns current_timestamp()
        $this->assertContains($rows[1]['Default'], ['CURRENT_TIMESTAMP', 'current_timestamp()']);
        $this->assertTrue($rows[2]['Default'] === 'oh hi');
    }

    public function testAddColumnFirst()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('new_id', 'integer', ['after' => MysqlAdapter::FIRST])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertSame('new_id', $rows[0]['Field']);
    }

    public static function integerDataProvider()
    {
        return [
            ['integer', [], 'int', '11', ''],
            ['integer', ['signed' => false], 'int', '10', ' unsigned'],
            ['smallinteger', [], 'smallint', '6', ''],
            ['smallinteger', ['signed' => false], 'smallint', '5', ' unsigned'],
            ['smallinteger', ['limit' => 3], 'smallint', '3', ''],
            ['biginteger', [], 'bigint', '20', ''],
            ['biginteger', ['signed' => false], 'bigint', '20', ' unsigned'],
            ['biginteger', ['limit' => 12], 'bigint', '20', ''],
        ];
    }

    #[DataProvider('integerDataProvider')]
    public function testIntegerColumnTypes($phinx_type, $options, $sql_type, $width, $extra)
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', $phinx_type, $options)
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');

        $type = $sql_type;
        if (!$this->usingMysql8()) {
            $type .= '(' . $width . ')';
        }
        $type .= $extra;
        $this->assertEquals($type, $rows[1]['Type']);
    }

    public function testAddStringColumnWithSignedEqualsFalse(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'string', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('varchar(255)', $rows[1]['Type']);
    }

    public function testAddStringColumnWithCustomCollation(): void
    {
        $table = new Table('table_custom_collation', ['collation' => 'utf8mb4_unicode_ci'], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('string_collation_default'));
        $this->assertFalse($table->hasColumn('string_collation_custom'));
        $table->addColumn('string_collation_default', 'string', [])->save();
        $table->addColumn('string_collation_custom', 'string', ['collation' => 'utf8mb4_unicode_ci'])->save();
        $rows = $this->adapter->fetchAll('SHOW FULL COLUMNS FROM table_custom_collation');
        $this->assertEquals('utf8mb4_unicode_ci', $rows[1]['Collation']);
        $this->assertEquals('utf8mb4_unicode_ci', $rows[2]['Collation']);
    }

    public function testRenameColumn(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('t', 'column2'));

        $table->renameColumn('column1', 'column2')->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenameColumnPreserveComment(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => 'comment1'])
              ->save();

        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('t', 'column2'));
        $columns = $this->adapter->fetchAll('SHOW FULL COLUMNS FROM t');
        $this->assertEquals('comment1', $columns[1]['Comment']);

        $table->renameColumn('column1', 'column2')->save();

        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
        $columns = $this->adapter->fetchAll('SHOW FULL COLUMNS FROM t');
        $this->assertEquals('comment1', $columns[1]['Comment']);
    }

    public function testRenameColumnWithDefaultGeneratedExtra(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('last_changed'));
        $table->addColumn('last_changed', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->save();
        $this->assertTrue($table->hasColumn('last_changed'));
        $table->renameColumn('last_changed', 'last_changed2')->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'last_changed'));
        $this->assertTrue($this->adapter->hasColumn('t', 'last_changed2'));
    }

    public function testRenamingANonExistentColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        try {
            $table->renameColumn('column2', 'column1')->save();
            $this->fail('Expected the adapter to throw an exception');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e),
            );
            $this->assertEquals('The specified column doesn\'t exist: column2', $e->getMessage());
        }
    }

    public function testChangeColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $table->changeColumn('column1', 'string')->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $newColumn2 = new Column();
        $newColumn2->setName('column2')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn2)->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testChangeColumnDefaultValue()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
              ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault('test1')
                   ->setName('column1')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals('test1', $rows[1]['Default']);
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
              ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault(0)
                   ->setName('column1')
                   ->setType('integer');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals('0', $rows[1]['Default']);
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
              ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault(null)
                   ->setName('column1')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNull($rows[1]['Default']);
    }

    public function testChangeColumnEnum()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $table->changeColumn('column1', 'enum', ['values' => ['a', 'b']])->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNull($rows[1]['Default']);
        $this->assertEquals("enum('a','b')", $rows[1]['Type']);
    }

    public static function binaryToBlobAutomaticConversionData()
    {
        return [
            // When creating binary with limit > 255, MySQL auto-converts to BLOB
            // input limit, expected SQL type name, expected column limit after round-trip
            [null, 'blob', MysqlAdapter::BLOB_REGULAR], // binary(null) becomes BLOB
            [64, 'tinyblob', MysqlAdapter::BLOB_TINY], // binary(64) becomes TINYBLOB
            [MysqlAdapter::BLOB_REGULAR - 20, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
            [MysqlAdapter::BLOB_REGULAR, 'blob', MysqlAdapter::BLOB_REGULAR],
            [MysqlAdapter::BLOB_REGULAR + 20, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
            [MysqlAdapter::BLOB_MEDIUM, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
            [MysqlAdapter::BLOB_MEDIUM + 20, 'longblob', MysqlAdapter::BLOB_LONG],
            [MysqlAdapter::BLOB_LONG, 'longblob', MysqlAdapter::BLOB_LONG],
        ];
    }

    #[DataProvider('binaryToBlobAutomaticConversionData')]
    public function testBinaryToBlobAutomaticConversion(?int $limit, string $expectedType, ?int $expectedLimit)
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'binary', ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $this->assertSame($expectedType, $columns[1]->getType());
        $this->assertSame($expectedLimit, $columns[1]->getLimit());
    }

    public static function varbinaryToBlobAutomaticConversionData()
    {
        return [
            // When creating varbinary with limit > 255, MySQL auto-converts to BLOB
            // input limit, expected SQL type name, expected column limit after round-trip
            [null, 'blob', MysqlAdapter::BLOB_REGULAR], // varbinary(null) becomes BLOB
            [64, 'tinyblob', MysqlAdapter::BLOB_TINY], // varbinary(64) becomes TINYBLOB
            [MysqlAdapter::BLOB_REGULAR - 20, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
            [MysqlAdapter::BLOB_REGULAR, 'blob', MysqlAdapter::BLOB_REGULAR],
            [MysqlAdapter::BLOB_REGULAR + 20, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
            [MysqlAdapter::BLOB_MEDIUM, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
            [MysqlAdapter::BLOB_MEDIUM + 20, 'longblob', MysqlAdapter::BLOB_LONG],
            [MysqlAdapter::BLOB_LONG, 'longblob', MysqlAdapter::BLOB_LONG],
        ];
    }

    #[DataProvider('varbinaryToBlobAutomaticConversionData')]
    public function testVarbinaryToBlobAutomaticConversion(?int $limit, string $expectedType, ?int $expectedLimit)
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'varbinary', ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $this->assertSame($expectedType, $columns[1]->getType());
        $this->assertSame($expectedLimit, $columns[1]->getLimit());
    }

    public static function blobColumnsData()
    {
        return [
          // BLOB columns with various limits - MySQL auto-selects appropriate BLOB subtype
          // input type, expected SQL type, input limit, expected column limit after round-trip
          // Tiny blobs
          ['tinyblob', 'tinyblob', null, MysqlAdapter::BLOB_TINY],
          ['tinyblob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['tinyblob', 'mediumblob', MysqlAdapter::BLOB_TINY + 20, MysqlAdapter::BLOB_MEDIUM],
          ['tinyblob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['tinyblob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
          // Regular blobs
          ['blob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['blob', 'blob', null, MysqlAdapter::BLOB_REGULAR],
          ['blob', 'blob', MysqlAdapter::BLOB_REGULAR, MysqlAdapter::BLOB_REGULAR],
          ['blob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['blob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
          // Medium blobs
          ['mediumblob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['mediumblob', 'blob', MysqlAdapter::BLOB_REGULAR, MysqlAdapter::BLOB_REGULAR],
          ['mediumblob', 'mediumblob', null, MysqlAdapter::BLOB_MEDIUM],
          ['mediumblob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['mediumblob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
          // Long blobs
          ['longblob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['longblob', 'blob', MysqlAdapter::BLOB_REGULAR, MysqlAdapter::BLOB_REGULAR],
          ['longblob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['longblob', 'longblob', null, MysqlAdapter::BLOB_LONG],
          ['longblob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
        ];
    }

    #[DataProvider('blobColumnsData')]
    public function testblobColumns(string $type, string $expectedType, ?int $limit, ?int $expectedLimit)
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('blob_col', $type, ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $this->assertSame($expectedType, $columns[1]->getType());
        $this->assertSame($expectedLimit, $columns[1]->getLimit());
    }

    public static function blobRoundTripData()
    {
        return [
            // type, limit, expected type after round-trip, expected limit after round-trip
            ['blob', null, 'blob', MysqlAdapter::BLOB_REGULAR],
            ['blob', MysqlAdapter::BLOB_REGULAR, 'blob', MysqlAdapter::BLOB_REGULAR],
            ['tinyblob', null, 'tinyblob', MysqlAdapter::BLOB_TINY],
            ['mediumblob', null, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
            ['longblob', null, 'longblob', MysqlAdapter::BLOB_LONG],
        ];
    }

    #[DataProvider('blobRoundTripData')]
    public function testBlobRoundTrip(string $type, ?int $limit, string $expectedType, int $expectedLimit)
    {
        // Create a table with a BLOB column
        $table = new Table('blob_round_trip_test', [], $this->adapter);
        $table->addColumn('blob_col', $type, ['limit' => $limit])
              ->save();

        // Read the column back from the database
        $columns = $this->adapter->getColumns('blob_round_trip_test');

        $blobColumn = $columns[1];
        $this->assertNotNull($blobColumn, 'BLOB column not found');
        $this->assertSame($expectedType, $blobColumn->getType(), 'Type mismatch after round-trip');
        $this->assertSame($expectedLimit, $blobColumn->getLimit(), 'Limit mismatch after round-trip');

        // Clean up
        $this->adapter->dropTable('blob_round_trip_test');
    }

    public function testTimestampInvalidLimit()
    {
        $this->adapter->connect();
        $version = $this->adapter->getConnection()->getDriver()->version();
        if (version_compare($version, '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $table = new Table('t', [], $this->adapter);

        $this->expectException(PDOException::class);

        $table->addColumn('column1', 'timestamp', ['limit' => 7])->save();
    }

    public function testDropColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $table->removeColumn('column1')->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
    }

    public static function columnsProvider()
    {
        return [
            ['column1', 'string', []],
            ['column2', 'smallinteger', []],
            ['column3', 'integer', []],
            ['column4', 'biginteger', []],
            ['column5', 'text', []],
            ['column6', 'float', []],
            ['column7', 'decimal', []],
            ['decimal_precision_scale', 'decimal', ['precision' => 10, 'scale' => 2]],
            ['decimal_precision_scale_zero', 'decimal', ['precision' => 65, 'scale' => 0]],
            ['decimal_limit', 'decimal', ['limit' => 10]],
            ['decimal_precision', 'decimal', ['precision' => 10]],
            ['column8', 'datetime', []],
            ['column9', 'time', []],
            ['column10', 'timestamp', []],
            ['column11', 'date', []],
            ['column12', 'blob', []], // binary with no limit becomes BLOB in MySQL
            ['column13', 'boolean', ['comment' => 'Lorem ipsum']],
            ['column14', 'string', ['limit' => 10]],
            ['column16', 'geometry', []],
            ['column17', 'point', []],
            ['column18', 'linestring', []],
            ['column19', 'polygon', []],
            ['column20', 'uuid', []],
        ];
    }

    #[DataProvider('columnsProvider')]
    public function testGetColumns($colName, $type, $options)
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());
        $this->assertEquals($type, $columns[1]->getType());

        if (isset($options['limit'])) {
            $this->assertEquals($options['limit'], $columns[1]->getLimit());
        }

        if (isset($options['values'])) {
            $this->assertEquals($options['values'], $columns[1]->getValues());
        }

        if (isset($options['precision'])) {
            $this->assertEquals($options['precision'], $columns[1]->getPrecision());
        }

        if (isset($options['scale'])) {
            $this->assertEquals($options['scale'], $columns[1]->getScale());
        }

        if (isset($options['comment'])) {
            $this->assertEquals($options['comment'], $columns[1]->getComment());
        }
    }

    public function testGetColumnsInteger()
    {
        $colName = 'column15';
        $type = 'integer';
        $options = ['limit' => 10];
        $table = new Table('t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());
        $this->assertEquals($type, $columns[1]->getType());

        $this->assertNull($columns[1]->getLimit());
    }

    public function testGetColumnsReservedTableName()
    {
        $table = new Table('group', [], $this->adapter);
        $table->addColumn('column1', 'string')->save();
        $columns = $this->adapter->getColumns('group');
        $this->assertCount(2, $columns);
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
        $this->adapter->connect();
        if (!$this->usingMysql8()) {
            $this->markTestSkipped('Cannot test index order on mysql versions less than 8');
        }
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->save();
        $this->assertFalse($table->hasIndexByName('table1_email_username'));
        $table->addIndex(['email', 'username'], ['name' => 'table1_email_username', 'order' => ['email' => 'DESC', 'username' => 'ASC']])
              ->save();
        $this->assertTrue($table->hasIndexByName('table1_email_username'));
        $rows = $this->adapter->fetchAll("SHOW INDEXES FROM table1 WHERE Key_name = 'table1_email_username' AND Column_name = 'email'");
        $emailOrder = $rows[0]['Collation'];
        $this->assertEquals($emailOrder, 'D');

        $rows = $this->adapter->fetchAll("SHOW INDEXES FROM table1 WHERE Key_name = 'table1_email_username' AND Column_name = 'username'");
        $emailOrder = $rows[0]['Collation'];
        $this->assertEquals($emailOrder, 'A');
    }

    public function testAddMultipleFulltextIndex()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->addColumn('bio', 'text')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $this->assertFalse($table->hasIndex('username'));
        $this->assertFalse($table->hasIndex('address'));
        $table->addIndex('email')
              ->addIndex('username', ['type' => 'fulltext'])
              ->addIndex('bio', ['type' => 'fulltext'])
              ->addIndex(['email', 'bio'], ['type' => 'fulltext'])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->assertTrue($table->hasIndex('username'));
        $this->assertTrue($table->hasIndex('bio'));
        $this->assertTrue($table->hasIndex(['email', 'bio']));
    }

    public function testAddIndexWithLimit()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['limit' => 50])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email"',
            $this->config['database'],
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 50);
    }

    public function testAddMultiIndexesWithLimitSpecifier()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->save();
        $this->assertFalse($table->hasIndex(['email', 'username']));
        $table->addIndex(['email', 'username'], ['limit' => [ 'email' => 3, 'username' => 2 ]])
              ->save();
        $this->assertTrue($table->hasIndex(['email', 'username']));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "email"',
            $this->config['database'],
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 3);
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "username"',
            $this->config['database'],
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 2);
    }

    public function testAddSingleIndexesWithLimitSpecifier()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addColumn('username', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['limit' => [ 'email' => 3, 2 ]])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "email"',
            $this->config['database'],
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 3);
    }

    public function testDropIndex()
    {
        // single column index
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $table->removeIndex(['email'])->save();
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $table2->removeIndex(['fname', 'lname'])->save();
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // index with name specified, but dropping it by column name
        $table3 = new Table('table3', [], $this->adapter);
        $table3->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'someindexname'])
              ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $table3->removeIndex(['email'])->save();
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new Table('table4', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $table4->removeIndex(['fname', 'lname'])->save();
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));

        // don't drop multiple column index when dropping single column
        $table2 = new Table('table5', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));

        try {
            $table2->removeIndex(['fname'])->save();
        } catch (InvalidArgumentException $e) {
        }
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));

        // don't drop multiple column index with name specified when dropping
        // single column
        $table4 = new Table('table6', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));

        try {
            $table4->removeIndex(['fname'])->save();
        } catch (InvalidArgumentException $e) {
        }

        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $table->removeIndexByName('myemailindex')->save();
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'twocolumnindex'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $table2->removeIndexByName('twocolumnindex')->save();
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));
    }

    public function testAddForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testAddForeignKeyForTableWithSignedPK()
    {
        $refTable = new Table('ref_table', ['signed' => true], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyWithMultipleColumns()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string', ['limit' => 8])
            ->addColumn('field2', 'string', ['limit' => 8])
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->addIndex(['field1', 'id'], ['unique' => true])
            ->addIndex(['id', 'field1', 'field2'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string', ['limit' => 8])
            ->addColumn('ref_table_field2', 'string', ['limit' => 8])
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

    public function testDropForeignKeyWithIdenticalMultipleColumns()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string', ['limit' => 8])
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $keyOne = (new ForeignKey())
            ->setName('ref_table_fk_1')
            ->setColumns(['ref_table_id', 'ref_table_field1'])
            ->setReferencedTable('ref_table')
            ->setReferencedColumns(['id', 'field1']);
        $keyTwo = (new ForeignKey())
            ->setName('ref_table_fk_2')
            ->setColumns(['ref_table_id', 'ref_table_field1'])
            ->setReferencedTable('ref_table')
            ->setReferencedColumns(['id', 'field1']);

        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string', ['limit' => 8])
            ->addForeignKey($keyOne)
            ->addForeignKey($keyTwo)
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
            ->addColumn('field1', 'string', ['limit' => 8])
            ->addIndex(['id', 'field1'])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string', ['limit' => 8])
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

    public function testDropForeignKeyCaseInsensitivity()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), ['REF_TABLE_ID']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyByName()
    {
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
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyForTableWithSignedPK()
    {
        $refTable = new Table('ref_table', ['signed' => true], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyAsString()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey('ref_table_id')->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    #[DataProvider('provideForeignKeysToCheck')]
    public function testHasForeignKey($tableDef, $key, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->execute('CREATE TABLE other(a int, b int, c int, key(a), key(b), key(a,b), key(a,b,c));');
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
            ['create table t(a int, `B` int, foreign key(a,`B`) references other(a,b))', ['a', 'b'], false],
            ['create table t(a int, `B` int, foreign key(a,`B`) references other(a,b))', ['a', 'B'], true],
            ['create table t(a int, b int, c int, foreign key(a,b,c) references other(a,b,c))', ['a', 'b'], false],
            ['create table t(a int, foreign key(a) references other(a))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t(`0` int, foreign key(`0`) references other(a))', '0', true],
            ['create table t(`0` int, foreign key(`0`) references other(a))', '0e0', false],
            ['create table t(`0e0` int, foreign key(`0e0`) references other(a))', '0', false],
        ];
    }

    public function testHasForeignKeyAsString()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), 'ref_table_id'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), 'ref_table_id2'));
    }

    public function testHasNamedForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

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

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint2'));
    }

    public function testHasForeignKeyWithConstraintForTableWithSignedPK()
    {
        $refTable = new Table('ref_table', ['signed' => true], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $key = (new ForeignKey())
            ->setName('my_constraint')
            ->setColumns(['ref_table_id'])
            ->setReferencedTable('ref_table')
            ->setReferencedColumns(['id']);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey($key)
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));
    }

    public function testsHasForeignKeyWithSchemaDotTableName()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($this->config['database'] . '.' . $table->getName(), ['ref_table_id']));
        $this->assertFalse($this->adapter->hasForeignKey($this->config['database'] . '.' . $table->getName(), ['ref_table_id2']));
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

    public function testAddColumnWithComment()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => $comment = 'Comments from "column1"'])
              ->save();

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT COLUMN_NAME, COLUMN_COMMENT
            FROM information_schema.columns
            WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='table1'
            ORDER BY ORDINAL_POSITION",
            $this->config['database'],
        ));
        $columnWithComment = $rows[1];

        $this->assertSame('column1', $columnWithComment['COLUMN_NAME'], "Didn't set column name correctly");
        $this->assertEquals($comment, $columnWithComment['COLUMN_COMMENT'], "Didn't set column comment correctly");
    }

    public function testAddColumnEnum()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $table->addColumn('column2', 'enum', ['values' => ['a', 'b']])->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));

        $comment = 'Comments from "column3"';
        $table->addColumn('column3', 'enum', ['values' => ['c', 'd'], 'null' => false, 'default' => 'd', 'comment' => $comment])->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column3'));

        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertEquals("enum('a','b')", $rows[2]['Type']);
        $this->assertEquals('YES', $rows[2]['Null']);
        $this->assertNull($rows[2]['Default']);
        $this->assertEquals("enum('c','d')", $rows[3]['Type']);
        $this->assertEquals('NO', $rows[3]['Null']);
        $this->assertEquals('d', $rows[3]['Default']);

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT COLUMN_NAME, COLUMN_COMMENT
            FROM information_schema.columns
            WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='t'
            ORDER BY ORDINAL_POSITION",
            $this->config['database'],
        ));
        $columnWithComment = $rows[3];
        $this->assertSame('column3', $columnWithComment['COLUMN_NAME'], "Didn't set column name correctly");
        $this->assertEquals($comment, $columnWithComment['COLUMN_COMMENT'], "Didn't set column comment correctly");
    }

    public function testAddGeoSpatialColumns()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('geo_geom'));
        $table->addColumn('geo_geom', 'geometry')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('geometry', $rows[1]['Type']);
    }

    public function testHasColumn()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $this->assertFalse($table->hasColumn('column2'));
        $this->assertTrue($table->hasColumn('column1'));
    }

    public function testHasColumnReservedName()
    {
        $tableQuoted = new Table('group', [], $this->adapter);
        $tableQuoted->addColumn('value', 'string')
                    ->save();

        $this->assertFalse($tableQuoted->hasColumn('column2'));
        $this->assertTrue($tableQuoted->hasColumn('value'));
    }

    public function testBulkInsertData()
    {
        $data = [
            [
                'column1' => 'value1',
                'column2' => 1,
            ],
            [
                'column1' => 'value2',
                'column2' => 2,
            ],
            [
                'column1' => 'value3',
                'column2' => 3,
            ],
        ];
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals('test', $rows[0]['column3']);
        $this->assertEquals('test', $rows[2]['column3']);
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
        $this->assertEquals('2024-01-01 00:00:00', $rows[1]['column2']);
        $this->assertEquals('2025-01-01 00:00:00', $rows[2]['column2']);
    }

    public function testInsertData()
    {
        $data = [
            [
                'column1' => 'value1',
                'column2' => 1,
            ],
            [
                'column1' => 'value2',
                'column2' => 2,
            ],
            [
                'column1' => 'value3',
                'column2' => 3,
                'column3' => 'foo',
            ],
        ];
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals('test', $rows[0]['column3']);
        $this->assertEquals('foo', $rows[2]['column3']);
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
        $this->assertEquals('2024-01-01 00:00:00', $rows[1]['column3']);
        $this->assertEquals('2025-01-01 00:00:00', $rows[2]['column3']);
    }

    public function testDumpCreateTable()
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $table = new Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test', 'null' => false])
            ->save();

        $actualOutput = join("\n", $this->out->messages());
        // MySQL version affects default collation (8.0.0+ uses utf8mb4_0900_ai_ci, older uses utf8mb4_general_ci)
        $this->assertMatchesRegularExpression(
            '/CREATE TABLE `table1` \(`id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT, `column1` VARCHAR\(255\) NOT NULL, `column2` INTEGER, `column3` VARCHAR\(255\) NOT NULL DEFAULT \'test\', PRIMARY KEY \(`id`\)\) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_(0900_ai_ci|general_ci);/',
            $actualOutput,
            'Passing the --dry-run option does not dump create table query to the output',
        );
    }

    /**
     * Creates the table "table1".
     * Then enables dry run mode and inserts a record.
     * Asserts that the insert statement is output and doesn't insert a record.
     */
    public function testDumpInsert()
    {
        $table = new Table('table1', [], $this->adapter);
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

        // Add this to be LF - CR/LF systems independent
        $expectedOutput = preg_replace('~\R~u', '', $expectedOutput);
        $actualOutput = preg_replace('~\R~u', '', $actualOutput);

        $this->assertStringContainsString($expectedOutput, trim($actualOutput), 'Passing the --dry-run option doesn\'t dump the insert to the output');

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll('assoc');
        $this->assertEquals(0, $res[0]['COUNT(*)']);
    }

    /**
     * Creates the table "table1".
     * Then enables dry run mode and inserts some records.
     * Asserts that output contains the insert statement and doesn't insert any record.
     */
    public function testDumpBulkinsert()
    {
        $table = new Table('table1', [], $this->adapter);
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
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $table = new Table('table1', ['id' => false, 'primary_key' => ['column1']], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->save();

        $table = new Table('table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1,
        ])->save();

        $actualOutput = join("\n", $this->out->messages());
        // Add this to be LF - CR/LF systems independent
        $actualOutput = preg_replace('~\R~u', '', $actualOutput);
        // MySQL version affects default collation (8.0.0+ uses utf8mb4_0900_ai_ci, older uses utf8mb4_general_ci)
        $this->assertMatchesRegularExpression(
            '/CREATE TABLE `table1` \(`column1` VARCHAR\(255\) NOT NULL, `column2` INTEGER, PRIMARY KEY \(`column1`\)\) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_(0900_ai_ci|general_ci);INSERT INTO `table1` \(`column1`, `column2`\) VALUES \(\'id1\', 1\);/',
            $actualOutput,
            'Passing the --dry-run option does not dump create and then insert table queries to the output',
        );
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

        $this->assertEquals(2, $stm->rowCount());

        $builder = $this->adapter->getSelectBuilder();
        $stm = $builder
            ->select('*')
            ->from('table1')
            ->where(['int_col >=' => 2])
            ->execute();

        $this->assertEquals(1, $stm->rowCount());
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

    public static function geometryTypeProvider()
    {
        return [
            [MysqlAdapter::PHINX_TYPE_GEOMETRY, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_POINT, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_LINESTRING, 'LINESTRING(30 10,10 30,40 40)'],
            [MysqlAdapter::PHINX_TYPE_POLYGON, 'POLYGON((30 10,40 40,20 40,10 20,30 10))'],
        ];
    }

    /**
     * @param string $type
     * @param string $geom
     */
    #[DataProvider('geometryTypeProvider')]
    public function testGeometrySridSupport($type, $geom)
    {
        $this->adapter->connect();
        if (!$this->usingMysql8()) {
            $this->markTestSkipped('Cannot test geometry srid on mysql versions less than 8');
        }

        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('geom', $type, ['srid' => 4326])
            ->save();

        $this->adapter->execute("INSERT INTO table1 (`geom`) VALUES (ST_GeomFromText('{$geom}', 4326))");
        $rows = $this->adapter->fetchAll('SELECT ST_AsWKT(geom) as wkt, ST_SRID(geom) as srid FROM table1');
        $this->assertCount(1, $rows);
        $this->assertSame($geom, $rows[0]['wkt']);
        $this->assertSame(4326, (int)$rows[0]['srid']);
    }

    /**
     * @param string $type
     * @param string $geom
     */
    #[DataProvider('geometryTypeProvider')]
    public function testGeometrySridThrowsInsertDifferentSrid($type, $geom)
    {
        $this->adapter->connect();
        if (!$this->usingMysql8()) {
            $this->markTestSkipped('Cannot test geometry srid on mysql versions less than 8');
        }

        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('geom', $type, ['srid' => 4326])
            ->save();

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("SQLSTATE[HY000]: General error: 3643 The SRID of the geometry does not match the SRID of the column 'geom'. The SRID of the geometry is 4322, but the SRID of the column is 4326. Consider changing the SRID of the geometry or the SRID property of the column.");
        $this->adapter->execute("INSERT INTO table1 (`geom`) VALUES (ST_GeomFromText('{$geom}', 4322))");
    }

    public static function defaultsCastAsExpressions()
    {
        return [
            [MysqlAdapter::PHINX_TYPE_JSON, '{"a": true}'],
            [MysqlAdapter::PHINX_TYPE_TEXT, 'abc'],
            [MysqlAdapter::PHINX_TYPE_GEOMETRY, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_POINT, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_LINESTRING, 'LINESTRING(30 10,10 30,40 40)'],
            [MysqlAdapter::PHINX_TYPE_POLYGON, 'POLYGON((30 10,40 40,20 40,10 20,30 10))'],
        ];
    }

    /**
     * MySQL 8 added support for specifying defaults for the BLOB, TEXT, GEOMETRY, and JSON data types,
     * however requiring that they be wrapped in expressions.
     *
     * @param string $type
     * @param string $default
     */
    #[DataProvider('defaultsCastAsExpressions')]
    public function testDefaultsCastAsExpressionsForCertainTypes(string $type, string $default): void
    {
        if (
            $this->usingMariaDb() && in_array($type, [
            MysqlAdapter::PHINX_TYPE_GEOMETRY,
            MysqlAdapter::PHINX_TYPE_POINT,
            MysqlAdapter::PHINX_TYPE_LINESTRING,
            MysqlAdapter::PHINX_TYPE_POLYGON,
            ])
        ) {
            $this->markTestSkipped('GIS is broken with MariaDB');
        }

        $this->adapter->connect();

        $table = new Table('table1', ['id' => false], $this->adapter);
        // MySQL 8.0+ and MariaDB 10.2+ support defaults for JSON/TEXT
        if (!$this->usingMysql8() && !$this->usingMariaDb()) {
            $this->expectException(PDOException::class);
        }
        $table
            ->addColumn('col_1', $type, ['default' => $default])
            ->create();

        $columns = $this->adapter->getColumns('table1');
        $this->assertCount(1, $columns);
        $this->assertSame('col_1', $columns[0]->getName());

        $actualDefault = $columns[0]->getDefault();
        // Normalize quote handling - both MariaDB and MySQL 8.0.13+ may return defaults with quotes
        if (str_starts_with($actualDefault, "'") && str_ends_with($actualDefault, "'")) {
            $actualDefault = substr($actualDefault, 1, -1);
        }
        $this->assertSame($default, $actualDefault);
    }

    public function testCreateTableWithPrecisionCurrentTimestamp()
    {
        $this->adapter->connect();
        (new Table('exampleCurrentTimestamp3', ['id' => false], $this->adapter))
            ->addColumn('timestamp_3', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP(3)',
                'limit' => 3,
            ])
            ->create();

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='exampleCurrentTimestamp3'",
            $this->config['database'],
        ));
        $colDef = $rows[0];
        $this->assertEqualsIgnoringCase('CURRENT_TIMESTAMP(3)', $colDef['COLUMN_DEFAULT']);
    }

    public function testAddCheckConstraint()
    {
        $table = new Table('check_table', [], $this->adapter);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
              ->create();

        $checkConstraint = new CheckConstraint('price_positive', 'price > 0');
        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);

        $this->assertTrue($this->adapter->hasCheckConstraint('check_table', 'price_positive'));
    }

    public function testAddCheckConstraintWithAutoGeneratedName()
    {
        $table = new Table('check_table2', [], $this->adapter);
        $table->addColumn('age', 'integer')
              ->create();

        $checkConstraint = new CheckConstraint('', 'age >= 18');

        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);

        $driver = $this->adapter->getConnection()->getDriver();
        assert($driver instanceof Mysql);

        $dialect = $driver->schemaDialect();
        $constraints = $dialect->describeCheckConstraints('check_table2');
        $this->assertCount(1, $constraints);
        $expected = $driver->isMariaDb() ? 'CONSTRAINT_1' : 'check_table2_chk_';
        $this->assertStringContainsString($expected, $constraints[0]['name']);
    }

    public function testHasCheckConstraint()
    {
        $table = new Table('check_table3', [], $this->adapter);
        $table->addColumn('quantity', 'integer')
              ->create();

        $checkConstraint = new CheckConstraint('quantity_positive', 'quantity > 0');
        $this->assertFalse($this->adapter->hasCheckConstraint('check_table3', 'quantity_positive'));

        $this->adapter->addCheckConstraint($table->getTable(), $checkConstraint);

        $this->assertTrue($this->adapter->hasCheckConstraint('check_table3', 'quantity_positive'));
    }

    public function testDropCheckConstraint()
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

    public function testCheckConstraintWithComplexExpression()
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
        $this->adapter->execute("INSERT INTO {$quotedTableName} (email, status) VALUES ('test@example.com', 'invalid')");
    }

    /**
     * Test that DECIMAL columns with scale=0 work correctly.
     *
     * This tests the fix for https://github.com/cakephp/phinx/pull/2377
     * In phinx, the boolean check `$column->getPrecision() && $column->getScale()`
     * would fail when scale is 0 because 0 is falsy in PHP.
     *
     * The 5.x branch uses CakePHP's database layer instead of phinx,
     * so we need to verify it handles scale=0 correctly.
     */
    public function testDecimalWithScaleZero()
    {
        // Create table with DECIMAL(65,0)
        $table = new Table('decimal_scale_zero_test', [], $this->adapter);
        $table->addColumn('amount', 'decimal', ['precision' => 65, 'scale' => 0])
            ->create();

        // Verify the column was created with correct precision and scale
        $columns = $this->adapter->getColumns('decimal_scale_zero_test');
        $amountColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === 'amount') {
                $amountColumn = $column;
                break;
            }
        }

        $this->assertNotNull($amountColumn, 'Amount column should exist');
        $this->assertEquals('decimal', $amountColumn->getType());
        $this->assertEquals(65, $amountColumn->getPrecision());
        $this->assertEquals(0, $amountColumn->getScale(), 'Scale should be 0, not null');

        // Verify the actual MySQL column definition
        $result = $this->adapter->fetchRow('SHOW CREATE TABLE `decimal_scale_zero_test`');
        $createTableSql = $result['Create Table'];

        // The CREATE TABLE should contain DECIMAL(65,0) - case insensitive
        $this->assertMatchesRegularExpression(
            '/decimal\(65,0\)/i',
            $createTableSql,
            'CREATE TABLE should contain DECIMAL(65,0) with scale=0 properly defined',
        );
    }
}
