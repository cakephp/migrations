<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\PostgresAdapter;
use Migrations\Db\Literal;
use Migrations\Db\Table;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Partition;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PostgresAdapterTest extends TestCase
{
    private PostgresAdapter $adapter;

    private array $config;

    private StubConsoleOutput $out;

    private ConsoleIo $io;

    /**
     * Check if Postgres is enabled in the current PHP
     *
     * @return bool
     */
    private function isPostgresAvailable()
    {
        static $available;

        if ($available === null) {
            $available = in_array('pgsql', PDO::getAvailableDrivers(), true);
        }

        return $available;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $config = ConnectionManager::getConfig('test');
        if ($config['scheme'] !== 'postgres') {
            $this->markTestSkipped('Postgres tests disabled.');
        }

        // Emulate the results of Util::parseDsn()
        $this->config = [
            'adapter' => 'postgres',
            'connection' => ConnectionManager::get('test'),
            'database' => $config['database'],
        ];

        if (!$this->isPostgresAvailable()) {
            $this->markTestSkipped('Postgres is not available.  Please install php-pdo-pgsql or equivalent package.');
        }

        $this->adapter = new PostgresAdapter($this->config, $this->getConsoleIo());

        $this->adapter->dropAllSchemas();
        $this->adapter->createSchema('public');

        $citext = $this->adapter->fetchRow("SELECT COUNT(*) AS enabled FROM pg_extension WHERE extname = 'citext'");
        if (!$citext['enabled']) {
            $this->adapter->query('CREATE EXTENSION IF NOT EXISTS citext');
        }

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
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

    private function usingPostgres10(): bool
    {
        $version = $this->adapter->getConnection()->getDriver()->version();

        return version_compare($version, '10.0.0', '>=');
    }

    public function testAdapterType(): void
    {
        $this->assertEquals('pgsql', $this->adapter->getAdapterType());
    }

    public function testConnection(): void
    {
        $this->assertInstanceOf(Connection::class, $this->adapter->getConnection());
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

    public function testQuoteSchemaName(): void
    {
        $this->assertEquals('"schema"', $this->adapter->quoteSchemaName('schema'));
        // No . is supported in schema name.
        $this->assertEquals('"schema"."schema"', $this->adapter->quoteSchemaName('schema.schema'));
    }

    public function testGetGlobalSchemaName(): void
    {
        $config = ConnectionManager::getConfig('test');
        $config['schema'] = 'test_schema';
        ConnectionManager::setConfig('test-schema', $config);
        // Emulate the results of Util::parseDsn()
        $this->config = [
            'adapter' => 'postgres',
            'connection' => ConnectionManager::get('test-schema'),
            'database' => $config['database'],
        ];

        $this->adapter = new PostgresAdapter($this->config, $this->getConsoleIo());

        $this->adapter->dropAllSchemas();
        $this->adapter->createSchema('test_schema');

        $this->adapter->disconnect();

        $this->assertEquals('"test_schema"."table"', $this->adapter->quoteTableName('table'));

        ConnectionManager::drop('test-schema');
    }

    public function testQuoteTableName(): void
    {
        $this->assertEquals('"public"."table"', $this->adapter->quoteTableName('table'));
        $this->assertEquals('"table"."table"', $this->adapter->quoteTableName('table.table'));
    }

    public function testQuoteColumnName(): void
    {
        $this->assertEquals('"string"', $this->adapter->quoteColumnName('string'));
        // No . is supported in column name.
        $this->assertEquals('"string"."string"', $this->adapter->quoteColumnName('string.string'));
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

    public function testCreateTableWithSchema(): void
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

        $this->adapter->dropSchema('nschema');
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

    public function testCreateTableWithConflictingPrimaryKeys(): void
    {
        $options = [
            'primary_key' => 'user_id',
        ];
        $table = new Table('atable', $options, $this->adapter);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot enable an auto incrementing ID field and a primary key');
        $table->addColumn('user_id', 'integer')->save();
    }

    public function testCreateTableWithPrimaryKeySetToImplicitId(): void
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

    public function testCreateTableWithPrimaryKeyArraySetToImplicitId(): void
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

    public function testCreateTableWithMultiplePrimaryKeyArraySetToImplicitId(): void
    {
        $options = [
            'primary_key' => ['id', 'user_id'],
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot enable an auto incrementing ID field and a primary key');
        $table->addColumn('user_id', 'integer')->save();
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
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    public function testCreateTableWithMultiplePrimaryKeysWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id'],
        ];
        $table = new Table('schema1.table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('schema1.table1', ['user_id', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('schema1.table1', ['tag_id', 'user_id']));
        $this->assertFalse($this->adapter->hasIndex('schema1.table1', ['tag_id', 'user_email']));

        $this->adapter->dropSchema('schema1');
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
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
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
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsNativeUuid(): void
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'nativeuuid')->save();
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
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

    public function testCreateTableWithIndexNullOrdering(): void
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $index = new Index();
        $index->setColumns('email')
            ->setOrder(['email' => 'ASC NULLS FIRST']);

        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex($index)
              ->save();
        $queries = $this->out->messages();
        $indexQuery = $queries[3];
        $this->assertStringContainsString('CREATE INDEX "table1_email"', $indexQuery);
        $this->assertStringContainsString('("email" ASC NULLS FIRST)', $indexQuery);
    }

    public function testCreateTableWithIndexConcurrently(): void
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $index = new Index();
        $index->setColumns('email')
            ->setType(Index::UNIQUE)
            ->setConcurrently(true);

        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex($index)
              ->save();
        $queries = $this->out->messages();
        $indexQuery = $queries[3];
        $this->assertStringContainsString('CREATE UNIQUE INDEX CONCURRENTLY "table1_email"', $indexQuery);
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
        $indexQuery = $queries[3];
        $this->assertStringContainsString('CREATE UNIQUE INDEX "table1_email"', $indexQuery);
        $this->assertStringContainsString('("email") WHERE is_verified = true', $indexQuery);
    }

    public function testAddPrimaryKey(): void
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

    public function testChangePrimaryKey(): void
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'integer')
            ->save();

        $table
            ->changePrimaryKey(['column2', 'column3'])
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column2', 'column3']));
    }

    public function testDropPrimaryKey(): void
    {
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testAddComment(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment1')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT description
                    FROM pg_description
                    JOIN pg_class ON pg_description.objoid = pg_class.oid
                    WHERE relname = '%s'",
                'table1',
            ),
        );
        $this->assertEquals('comment1', $rows[0]['description']);
    }

    public function testChangeComment(): void
    {
        $table = new Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment2')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT description
                    FROM pg_description
                    JOIN pg_class ON pg_description.objoid = pg_class.oid
                    WHERE relname = '%s'",
                'table1',
            ),
        );
        $this->assertEquals('comment2', $rows[0]['description']);
    }

    public function testDropComment(): void
    {
        $table = new Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment(null)
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT description
                    FROM pg_description
                    JOIN pg_class ON pg_description.objoid = pg_class.oid
                    WHERE relname = '%s'",
                'table1',
            ),
        );
        $this->assertEmpty($rows);
    }

    public function testRenameTable(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));

        $table->rename('table2')->save();
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }

    public function testRenameTableWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        $table = new Table('schema1.table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('schema1.table1'));
        $this->assertFalse($this->adapter->hasTable('schema1.table2'));
        $this->adapter->renameTable('schema1.table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('schema1.table1'));
        $this->assertTrue($this->adapter->hasTable('schema1.table2'));

        $this->adapter->dropSchema('schema1');
    }

    public function testAddColumn(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
              ->save();
        $this->assertTrue($table->hasColumn('email'));
    }

    public function testAddColumnWithDefaultValue(): void
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

    public function testAddColumnWithDefaultZero(): void
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

    public function testAddColumnWithAutoIdentity(): void
    {
        if (!$this->usingPostgres10()) {
            $this->markTestSkipped('Test Skipped because of PostgreSQL version is < 10.0');
        }
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'id') {
                $this->assertTrue($column->getIdentity());
                $this->assertEquals(PostgresAdapter::GENERATED_BY_DEFAULT, $column->getGenerated());
            }
        }
    }

    /**
     * Test that shims from PHINX_TYPE_JSONB to 'json' type work.
     */
    public function testAddColumnJsonbCompat(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('config'));
        $table->addColumn('config', 'jsonb')
              ->save();
        $this->assertTrue($table->hasColumn('config'));
    }

    public static function providerAddColumnIdentity(): array
    {
        return [
            [PostgresAdapter::GENERATED_ALWAYS, true], //testAddColumnWithIdentityAlways
            [PostgresAdapter::GENERATED_BY_DEFAULT, false], //testAddColumnWithIdentityDefault
            [PostgresAdapter::GENERATED_BY_DEFAULT, true],
        ];
    }

    #[DataProvider('providerAddColumnIdentity')]
    public function testAddColumnIdentity(string $generated, bool $addToColumn): void
    {
        if (!$this->usingPostgres10()) {
            $this->markTestSkipped('Test Skipped because of PostgreSQL version is < 10.0');
        }
        $table = new Table('table1', ['id' => false], $this->adapter);
        $table->save();

        $options = ['identity' => true];
        if ($addToColumn) {
            $options['generated'] = $generated;
        }
        $table->addColumn('id', 'integer', $options)
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'id') {
                $this->assertEquals((bool)$generated, $column->getIdentity(), 'identity value does not match');
                $this->assertEquals($generated, $column->getGenerated(), 'generated value does not match');
            }
        }
    }

    public function testAddColumnWithDefaultBoolean(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_true', 'boolean', ['default' => true])
              ->addColumn('default_false', 'boolean', ['default' => false])
              ->addColumn('default_null', 'boolean', ['default' => null, 'null' => true])
              ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'default_true') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals(1, $column->getDefault());
            }
            if ($column->getName() === 'default_false') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals(0, $column->getDefault());
            }
            if ($column->getName() === 'default_null') {
                $this->assertNull($column->getDefault());
            }
        }
    }

    public function testAddColumnWithBooleanIgnoreLimitCastDefault(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('limit_bool_true', 'boolean', [
            'default' => 1,
            'limit' => 1,
            'null' => false,
        ]);
        $table->addColumn('limit_bool_false', 'boolean', [
            'default' => 0,
            'limit' => 0,
            'null' => false,
        ]);
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        $this->assertCount(3, $columns);
        /**
         * @var Column $column
         */
        $column = $columns[1];
        $this->assertSame('limit_bool_true', $column->getName());
        $this->assertNotNull($column->getDefault());
        $this->assertSame(1, $column->getDefault());
        $this->assertNull($column->getLimit());

        $column = $columns[2];
        $this->assertSame('limit_bool_false', $column->getName());
        $this->assertNotNull($column->getDefault());
        $this->assertSame(0, $column->getDefault());
        $this->assertNull($column->getLimit());
    }

    public function testAddColumnWithComment(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $this->assertFalse($table->hasColumn('email'));

        $table->addColumn('email', 'string', ['comment' => $comment = 'Comments from column "email"'])
              ->save();

        $this->assertTrue($table->hasColumn('email'));

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'email\'',
        );

        $this->assertEquals(
            $comment,
            $row['column_comment'],
            'The column comment was not set when you used addColumn()',
        );
    }

    public function testAddStringWithLimit(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('string1', 'string', ['limit' => 10])
                ->addColumn('char1', 'char', ['limit' => 20])
                ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'string1') {
                    $this->assertEquals('10', $column->getLimit());
            }

            if ($column->getName() === 'char1') {
                    $this->assertEquals('20', $column->getLimit());
            }
        }
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
                $this->assertNull($column->getScale());
            }
        }
    }

    public function testAddTimestampWithPrecision(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('timestamp1', 'timestamp', ['precision' => 0])
            ->addColumn('timestamp2', 'timestamp', ['precision' => 4])
            ->addColumn('timestamp3', 'timestamp')
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'timestamp1') {
                $this->assertEquals(0, $column->getPrecision());
            }

            if ($column->getName() === 'timestamp2') {
                $this->assertEquals(4, $column->getPrecision());
            }

            if ($column->getName() === 'timestamp3') {
                $this->assertEquals(6, $column->getPrecision());
            }
        }
    }

    public function testRenameColumn(): void
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

    public function testRenameColumnIsCaseSensitive(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('columnOne', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'columnOne'));
        $this->assertFalse($this->adapter->hasColumn('t', 'columnTwo'));
        $this->adapter->renameColumn('t', 'columnOne', 'columnTwo');
        $this->assertFalse($this->adapter->hasColumn('t', 'columnOne'));
        $this->assertTrue($this->adapter->hasColumn('t', 'columnTwo'));
    }

    public function testRenamingANonExistentColumn(): void
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
                'Expected exception of type InvalidArgumentException, got ' . $e::class,
            );
            $this->assertEquals('The specified column does not exist: column2', $e->getMessage());
        }
    }

    public function testChangeColumn(): void
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

    public static function providerChangeColumnIdentity(): array
    {
        return [
            [PostgresAdapter::GENERATED_ALWAYS], //testChangeColumnAddIdentityAlways
            [PostgresAdapter::GENERATED_BY_DEFAULT], //testChangeColumnAddIdentityDefault
        ];
    }

    #[DataProvider('providerChangeColumnIdentity')]
    public function testChangeColumnIdentity(string $generated): void
    {
        if (!$this->usingPostgres10()) {
            $this->markTestSkipped('Test Skipped because of PostgreSQL version is < 10.0');
        }
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'integer');
        $table->save();

        $table->changeColumn('column1', 'integer', ['identity' => true, 'generated' => PostgresAdapter::GENERATED_ALWAYS]);
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertTrue($column->getIdentity());
                $this->assertEquals(PostgresAdapter::GENERATED_ALWAYS, $column->getGenerated());
            }
        }
    }

    public function testChangeColumnDropIdentity(): void
    {
        if (!$this->usingPostgres10()) {
            $this->markTestSkipped('Test Skipped because of PostgreSQL version is < 10.0');
        }
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->changeColumn('id', 'integer', ['identity' => false]);
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'id') {
                $this->assertFalse($column->getIdentity());
            }
        }
    }

    public function testChangeColumnChangeIdentity(): void
    {
        if (!$this->usingPostgres10()) {
            $this->markTestSkipped('Test Skipped because of PostgreSQL version is < 10.0');
        }
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->changeColumn('id', 'integer', ['identity' => true, 'generated' => PostgresAdapter::GENERATED_BY_DEFAULT]);
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() === 'id') {
                $this->assertTrue($column->getIdentity());
                $this->assertEquals(PostgresAdapter::GENERATED_BY_DEFAULT, $column->getGenerated());
            }
        }
    }

    public static function integersProvider(): array
    {
        return [
            ['smallinteger', 32767],
            ['integer', 2147483647],
            ['biginteger', 9223372036854775807],
        ];
    }

    #[DataProvider('integersProvider')]
    public function testChangeColumnFromTextToInteger(string $type, int $value): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'text')
            ->insert(['column1' => (string)$value])
            ->save();

        $table->changeColumn('column1', $type)->save();
        $columnType = $table->getColumn('column1')->getType();
        $this->assertSame($columnType, $type);

        $row = $this->adapter->fetchRow('SELECT * FROM t');
        $this->assertSame($value, $row['column1']);
    }

    public function testChangeBooleanOptions(): void
    {
        $table = new Table('t', ['id' => false], $this->adapter);
        $table->addColumn('my_bool', 'boolean', ['default' => true, 'null' => true])
              ->create();
        $table
            ->insert([
                ['my_bool' => true],
                ['my_bool' => false],
                ['my_bool' => null],
            ])
            ->update();
        $table->changeColumn('my_bool', 'boolean', ['default' => false, 'null' => true])->update();
        $columns = $this->adapter->getColumns('t');
        $this->assertSame(0, $columns[0]->getDefault());

        $rows = $this->adapter->fetchAll('SELECT * FROM t');
        $this->assertCount(3, $rows);
        $this->assertSame([true, false, null], array_map(function (array $row) {
            return $row['my_bool'];
        }, $rows));
    }

    public function testChangeColumnFromIntegerToBoolean(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['default' => 0])
              ->save();
        $table->changeColumn('column1', 'boolean', ['default' => 't', 'null' => true])
        ->save();
        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertTrue($column->isNull());
                $this->assertSame(1, $column->getDefault());
            }
        }
    }

    public function testChangeColumnCharToUuid(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'char', ['default' => null, 'limit' => 36])
              ->save();
        $table->changeColumn('column1', 'uuid', ['default' => null, 'null' => true])
        ->save();
        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertTrue($column->isNull());
                $this->assertNull($column->getDefault());
                $columnType = $table->getColumn('column1')->getType();
                $this->assertSame($columnType, 'uuid');
            }
        }
    }

    public function testChangeColumnCharToNativeUuid(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'char', ['default' => null, 'limit' => 36])
              ->save();
        $table->changeColumn('column1', 'nativeuuid', ['default' => null, 'null' => true])
        ->save();
        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertTrue($column->isNull());
                $this->assertNull($column->getDefault());
                $columnType = $table->getColumn('column1')->getType();
                $this->assertSame($columnType, 'uuid');
            }
        }
    }

    public function testChangeColumnWithDefault(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $newColumn1 = new Column();
        $newColumn1->setName('column1')
                   ->setType('string')
                   ->setNull(true);

        $newColumn1->setDefault('Test');
        $table->changeColumn('column1', $newColumn1)->save();

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertTrue($column->isNull());
                $this->assertStringContainsString('Test', $column->getDefault());
            }
        }
    }

    public function testChangeColumnWithDropDefault(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'Test'])
              ->save();

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertStringContainsString('Test', $column->getDefault());
            }
        }

        $newColumn1 = new Column();
        $newColumn1->setName('column1')
                   ->setType('string');

        $table->changeColumn('column1', $newColumn1)->save();

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertNull($column->getDefault());
            }
        }
    }

    public function testDropColumn(): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $table->removeColumn('column1')->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
    }

    public static function columnsProvider(): array
    {
        return [
            ['column1', 'string', []],
            ['column2', 'smallinteger', []],
            ['column2_1', 'integer', []],
            ['column3', 'biginteger', []],
            ['column4', 'text', []],
            ['column5', 'float', [], 'float'],
            ['column6', 'decimal', []],
            ['column7', 'datetime', [], 'timestampfractional'],
            ['column9', 'timestamp', [], 'timestampfractional'],
            ['column10', 'date', []],
            ['column11', 'binary', []],
            ['column12', 'boolean', []],
            ['column13', 'string', ['limit' => 10]],
            ['decimal_precision_scale', 'decimal', ['precision' => 10, 'scale' => 2]],
            ['decimal_limit', 'decimal', ['limit' => 10]],
            ['decimal_precision', 'decimal', ['precision' => 10]],
        ];
    }

    #[DataProvider('columnsProvider')]
    public function testGetColumns(string $colName, string $type, array $options, ?string $actualType = null): void
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());

        if (!$actualType) {
            $actualType = $type;
        }

        if (is_string($columns[1]->getType())) {
            $this->assertEquals($actualType, $columns[1]->getType());
        } else {
            $this->assertEquals(['name' => $actualType] + $options, $columns[1]->getType());
        }
    }

    #[DataProvider('columnsProvider')]
    public function testGetColumnsWithSchema(string $colName, string $type, array $options, ?string $actualType = null): void
    {
        $this->adapter->createSchema('tschema');

        $table = new Table('tschema.t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('tschema.t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());

        if (!$actualType) {
            $actualType = $type;
        }

        if (is_string($columns[1]->getType())) {
            $this->assertEquals($actualType, $columns[1]->getType());
        } else {
            $this->assertEquals(['name' => $actualType] + $options, $columns[1]->getType());
        }

        $this->adapter->dropSchema('tschema');
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

    public function testAddIndexWithSort(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->save();
        $this->assertFalse($table->hasIndexByName('table1_email_username'));
        $table->addIndex(['email', 'username'], ['name' => 'table1_email_username', 'order' => ['email' => 'DESC', 'username' => 'ASC']])
          ->save();
        $this->assertTrue($table->hasIndexByName('table1_email_username'));
        $rows = $this->adapter->fetchAll("SELECT CASE o.option & 1 WHEN 1 THEN 'DESC' ELSE 'ASC' END as sort_order
                        FROM pg_index AS i
                        JOIN pg_class AS trel ON trel.oid = i.indrelid
                        JOIN pg_namespace AS tnsp ON trel.relnamespace = tnsp.oid
                        JOIN pg_class AS irel ON irel.oid = i.indexrelid
                        CROSS JOIN LATERAL unnest (i.indkey) WITH ORDINALITY AS c (colnum, ordinality)
                        LEFT JOIN LATERAL unnest (i.indoption) WITH ORDINALITY AS o (option, ordinality)
                        ON c.ordinality = o.ordinality
                        JOIN pg_attribute AS a ON trel.oid = a.attrelid AND a.attnum = c.colnum
                        WHERE trel.relname = 'table1'
                        AND irel.relname = 'table1_email_username'
                        AND a.attname = 'email'
                        GROUP BY o.option, tnsp.nspname, trel.relname, irel.relname");
        $emailOrder = $rows[0];
        $this->assertEquals($emailOrder['sort_order'], 'DESC');
        $rows = $this->adapter->fetchAll("SELECT CASE o.option & 1 WHEN 1 THEN 'DESC' ELSE 'ASC' END as sort_order
                        FROM pg_index AS i
                        JOIN pg_class AS trel ON trel.oid = i.indrelid
                        JOIN pg_namespace AS tnsp ON trel.relnamespace = tnsp.oid
                        JOIN pg_class AS irel ON irel.oid = i.indexrelid
                        CROSS JOIN LATERAL unnest (i.indkey) WITH ORDINALITY AS c (colnum, ordinality)
                        LEFT JOIN LATERAL unnest (i.indoption) WITH ORDINALITY AS o (option, ordinality)
                        ON c.ordinality = o.ordinality
                        JOIN pg_attribute AS a ON trel.oid = a.attrelid AND a.attnum = c.colnum
                        WHERE trel.relname = 'table1'
                        AND irel.relname = 'table1_email_username'
                        AND a.attname = 'username'
                        GROUP BY o.option, tnsp.nspname, trel.relname, irel.relname");
        $emailOrder = $rows[0];
        $this->assertEquals($emailOrder['sort_order'], 'ASC');
    }

    public function testAddIndexWithIncludeColumns(): void
    {
        if (!version_compare($this->adapter->fetchAll('SHOW server_version;')[0]['server_version'], '11.0.0', '>=')) {
            $this->markTestSkipped('Cannot test index include collumns (non-key columns) on postgresql versions less than 11');
        }

        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('firstname', 'string')
              ->addColumn('lastname', 'string')
              ->save();
        $this->assertFalse($table->hasIndexByName('table1_include_idx'));
        $table->addIndex(['email'], ['name' => 'table1_include_idx', 'include' => ['firstname', 'lastname']])
              ->save();
        $this->assertTrue($table->hasIndexByName('table1_include_idx'));
        $rows = $this->adapter->fetchAll("SELECT CASE WHEN attnum <= indnkeyatts  THEN 'KEY' ELSE 'INCLUDED' END as index_column
                        FROM pg_index ix
                        JOIN pg_class t ON ix.indrelid = t.oid
                        JOIN pg_class i ON ix.indexrelid = i.oid
                        JOIN pg_attribute a ON i.oid = a.attrelid
                        JOIN pg_namespace nsp ON t.relnamespace = nsp.oid
                        WHERE nsp.nspname = 'public'
                        AND t.relkind = 'r'
                        AND t.relname = 'table1'
                        AND a.attname = 'email'");
        $indexColumn = $rows[0];
        $this->assertEquals($indexColumn['index_column'], 'KEY');
            $rows = $this->adapter->fetchAll("SELECT CASE WHEN attnum <= indnkeyatts  THEN 'KEY' ELSE 'INCLUDED' END as index_column
                        FROM pg_index ix
                        JOIN pg_class t ON ix.indrelid = t.oid
                        JOIN pg_class i ON ix.indexrelid = i.oid
                        JOIN pg_attribute a ON i.oid = a.attrelid
                        JOIN pg_namespace nsp ON t.relnamespace = nsp.oid
                        WHERE nsp.nspname = 'public'
                        AND t.relkind = 'r'
                        AND t.relname = 'table1'
                        AND a.attname = 'firstname'");
        $indexColumn = $rows[0];
        $this->assertEquals($indexColumn['index_column'], 'INCLUDED');
        $rows = $this->adapter->fetchAll("SELECT CASE WHEN attnum <= indnkeyatts  THEN 'KEY' ELSE 'INCLUDED' END as index_column
                        FROM pg_index ix
                        JOIN pg_class t ON ix.indrelid = t.oid
                        JOIN pg_class i ON ix.indexrelid = i.oid
                        JOIN pg_attribute a ON i.oid = a.attrelid
                        JOIN pg_namespace nsp ON t.relnamespace = nsp.oid
                        WHERE nsp.nspname = 'public'
                        AND t.relkind = 'r'
                        AND t.relname = 'table1'
                        AND a.attname = 'lastname'");
        $indexColumn = $rows[0];
        $this->assertEquals($indexColumn['index_column'], 'INCLUDED');
    }

    public function testAddIndexWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        $table = new Table('schema1.table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));

        $this->adapter->dropSchema('schema1');
    }

    public function testAddIndexWithNameWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        $table = new Table('schema1.table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['name' => 'indexEmail'])
            ->save();
        $this->assertTrue($table->hasIndex('email'));

        $this->adapter->dropSchema('schema1');
    }

    public function testAddIndexIsCaseSensitive(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('theEmail', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('theEmail'));
        $table->addIndex('theEmail')
            ->save();
        $this->assertTrue($table->hasIndex('theEmail'));
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

    public function testDropIndexWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        // single column index
        $table = new Table('schema1.table5', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('schema1.table6', [], $this->adapter);
        $table2->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'])
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table2->getName(), ['fname', 'lname']);
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // index with name specified, but dropping it by column name
        $table3 = new Table('schema1.table7', [], $this->adapter);
        $table3->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'someIndexName'])
            ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $this->adapter->dropIndex($table3->getName(), 'email');
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new Table('schema1.table8', [], $this->adapter);
        $table4->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
            ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table4->getName(), ['fname', 'lname']);
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));

        $this->adapter->dropSchema('schema1');
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
               ->addIndex(
                   ['fname', 'lname'],
                   ['name' => 'twocolumnuniqueindex', 'unique' => true],
               )
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnuniqueindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByNameWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        // single column index
        $table = new Table('schema1.Table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailIndex'])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndexByName($table->getName(), 'myemailIndex');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('schema1.table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(
                ['fname', 'lname'],
                ['name' => 'twocolumnuniqueindex', 'unique' => true],
            )
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnuniqueindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        $this->adapter->dropSchema('schema1');
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

    public function testAddForeignKeyWithSchema(): void
    {
        $this->adapter->createSchema('schema1');
        $this->adapter->createSchema('schema2');

        $refTable = new Table('schema1.ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('schema2.table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'schema1.ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->dropSchema('schema1');
        $this->adapter->dropSchema('schema2');
    }

    public function testAddForeignKeyDeferrable(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(
                ['ref_table_id'],
                'ref_table',
                ['id'],
                [
                    'deferrable' => 'DEFERRED',
                ],
            )
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKey(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
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

    public function testDropForeignKeyWithIdenticalMultipleColumns(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string')
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
            ->addColumn('ref_table_field1', 'string')
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

    public function testDropForeignKeyCaseSensitivity(): void
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
            implode(', ', ['ref_table_id']),
        ));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);
    }

    public function testDropForeignKeyByName(): void
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

    #[DataProvider('provideForeignKeysToCheck')]
    public function testHasForeignKey(string $tableDef, string|array $key, bool $exp): void
    {
        $conn = $this->adapter->getConnection();
        $conn->execute('CREATE TABLE other(a int, b int, c int, unique(a), unique(b), unique(a,b), unique(a,b,c));');
        $conn->execute($tableDef);
        $this->assertSame($exp, $this->adapter->hasForeignKey('t', $key));
    }

    public static function provideForeignKeysToCheck(): array
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
            ['create table t(a int, "B" int, foreign key(a,"B") references other(a,b))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', ['a', 'B'], false],
            ['create table t(a int, b int, c int, foreign key(a,b,c) references other(a,b,c))', ['a', 'b'], false],
            ['create table t(a int, foreign key(a) references other(a))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t("0" int, foreign key("0") references other(a))', '0', true],
            ['create table t("0" int, foreign key("0") references other(a))', '0e0', false],
            ['create table t("0e0" int, foreign key("0e0") references other(a))', '0', false],
        ];
    }

    public function testHasNamedForeignKey(): void
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
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey($key)
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint2'));
    }

    public function testDropForeignKeyWithSchema(): void
    {
        $this->adapter->createSchema('schema1');
        $this->adapter->createSchema('schema2');

        $refTable = new Table('schema1.ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('schema2.table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'schema1.ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->dropSchema('schema1');
        $this->adapter->dropSchema('schema2');
    }

    public function testDropForeignKeyNotDroppingPrimaryKey(): void
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [
            'id' => false,
            'primary_key' => ['ref_table_id'],
        ], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertTrue($this->adapter->hasIndexByName('table', 'table_pkey'));
    }

    public function testHasDatabase(): void
    {
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

    public function testCreateSchema(): void
    {
        $this->adapter->createSchema('foo');
        $this->assertTrue($this->adapter->hasSchema('foo'));
    }

    public function testDropSchema(): void
    {
        $this->adapter->createSchema('foo');
        $this->assertTrue($this->adapter->hasSchema('foo'));
        $this->adapter->dropSchema('foo');
        $this->assertFalse($this->adapter->hasSchema('foo'));
    }

    public function testDropAllSchemas(): void
    {
        $this->adapter->createSchema('foo');
        $this->adapter->createSchema('bar');

        $this->assertTrue($this->adapter->hasSchema('foo'));
        $this->assertTrue($this->adapter->hasSchema('bar'));
        $this->adapter->dropAllSchemas();
        $this->assertFalse($this->adapter->hasSchema('foo'));
        $this->assertFalse($this->adapter->hasSchema('bar'));
    }

    public function testCreateTableWithComment(): void
    {
        $tableComment = 'Table comment';
        $table = new Table('ntable', ['comment' => $tableComment], $this->adapter);
        $table->addColumn('realname', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $rows = $this->adapter->fetchAll(
            sprintf(
                'SELECT description FROM pg_description JOIN pg_class ON pg_description.objoid = ' .
                "pg_class.oid WHERE relname = '%s'",
                'ntable',
            ),
        );

        $this->assertEquals($tableComment, $rows[0]['description'], 'Dont set table comment correctly');
    }

    public function testCanAddColumnComment(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn(
            'field1',
            'string',
            ['comment' => $comment = 'Comments from column "field1"'],
        )->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'field1\'',
        );

        $this->assertEquals($comment, $row['column_comment'], 'Dont set column comment correctly');
    }

    public function testCanAddCommentForColumnWithReservedName(): void
    {
        $table = new Table('user', [], $this->adapter);
        $table->addColumn('index', 'string', ['comment' => $comment = 'Comments from column "index"'])
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'user\'
            AND cols.column_name = \'index\'',
        );

        $this->assertEquals(
            $comment,
            $row['column_comment'],
            'Dont set column comment correctly for tables or columns with reserved names',
        );
    }

    #[Depends('testCanAddColumnComment')]
    public function testCanChangeColumnComment(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
              ->save();

        $table->changeColumn(
            'field1',
            'string',
            ['comment' => $comment = 'New Comments from column "field1"'],
        )->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'field1\'',
        );

        $this->assertEquals($comment, $row['column_comment'], 'Dont change column comment correctly');
    }

    #[Depends('testCanAddColumnComment')]
    public function testCanRemoveColumnComment(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
              ->save();

        $table->changeColumn('field1', 'string', ['comment' => 'null'])
              ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'field1\'',
        );

        $this->assertEmpty($row['column_comment'], 'Dont remove column comment correctly');
    }

    #[Depends('testCanAddColumnComment')]
    public function testCanAddMultipleCommentsToOneTable(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('comment1', 'string', [
            'comment' => $comment1 = 'first comment',
            ])
            ->addColumn('comment2', 'string', [
            'comment' => $comment2 = 'second comment',
            ])
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'comment1\'',
        );

        $this->assertEquals($comment1, $row['column_comment'], 'Could not create first column comment');

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'comment2\'',
        );

        $this->assertEquals($comment2, $row['column_comment'], 'Could not create second column comment');
    }

    #[Depends('testCanAddColumnComment')]
    public function testColumnsAreResetBetweenTables(): void
    {
        $table = new Table('widgets', [], $this->adapter);
        $table->addColumn('transport', 'string', [
            'comment' => $comment = 'One of: car, boat, truck, plane, train',
            ])
            ->save();

        $table = new Table('things', [], $this->adapter);
        $table->addColumn('speed', 'integer')
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . $this->config['database'] . '\'
            AND cols.table_name=\'widgets\'
            AND cols.column_name = \'transport\'',
        );

        $this->assertEquals($comment, $row['column_comment'], 'Could not create column comment');
    }

    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testForeignKeysAreProperlyEscaped(): void
    {
        $userId = 'user';
        $sessionId = 'session';

        $local = new Table('users', ['id' => $userId], $this->adapter);
        $local->create();

        $foreign = new Table(
            'sessions',
            ['id' => $sessionId],
            $this->adapter,
        );
        $foreign->addColumn('user', 'integer')
                ->addForeignKey('user', 'users', $userId)
                ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));
    }

    public function testForeignKeysAreProperlyEscapedWithSchema(): void
    {
        $this->adapter->createSchema('schema_users');

        $userId = 'user';
        $sessionId = 'session';

        $local = new Table(
            'schema_users.users',
            ['id' => $userId],
            $this->adapter,
        );
        $local->create();

        $foreign = new Table(
            'schema_users.sessions',
            ['id' => $sessionId],
            $this->adapter,
        );
        $foreign->addColumn('user', 'integer')
            ->addForeignKey('user', 'schema_users.users', $userId)
            ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));

        $this->adapter->dropSchema('schema_users');
    }

    public function testForeignKeysAreProperlyEscapedWithSchema2(): void
    {
        $this->adapter->createSchema('schema_users');
        $this->adapter->createSchema('schema_sessions');

        $userId = 'user';
        $sessionId = 'session';

        $local = new Table(
            'schema_users.users',
            ['id' => $userId],
            $this->adapter,
        );
        $local->create();

        $foreign = new Table(
            'schema_sessions.sessions',
            ['id' => $sessionId],
            $this->adapter,
        );
        $foreign->addColumn('user', 'integer')
            ->addForeignKey('user', 'schema_users.users', $userId)
            ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));

        $this->adapter->dropSchema('schema_users');
        $this->adapter->dropSchema('schema_sessions');
    }

    public function testTimestampWithTimezone(): void
    {
        $table = new Table('tztable', ['id' => false], $this->adapter);
        $table
            ->addColumn('timestamp_tz', 'timestamp', ['timezone' => true])
            /* default for timezone option is false */
            ->addColumn('time_notz', 'timestamp')
            ->save();

        $this->assertTrue($this->adapter->hasColumn('tztable', 'timestamp_tz'));
        $this->assertTrue($this->adapter->hasColumn('tztable', 'time_notz'));

        $columns = $this->adapter->getColumns('tztable');
        foreach ($columns as $column) {
            if (str_ends_with((string)$column->getName(), 'notz')) {
                $this->assertFalse($column->isTimezone(), 'column: ' . $column->getName());
            } else {
                $this->assertTrue($column->isTimezone(), 'column: ' . $column->getName());
            }
        }
    }

    public function testTimestampWithTimezoneWithSchema(): void
    {
        $this->adapter->createSchema('tzschema');

        $table = new Table('tzschema.tztable', ['id' => false], $this->adapter);
        $table
            ->addColumn('timestamp_tz', 'timestamp', ['timezone' => true])
            /* default for timezone option is false */
            ->addColumn('time_notz', 'timestamp')
            ->save();

        $this->assertTrue($this->adapter->hasColumn('tzschema.tztable', 'timestamp_tz'));
        $this->assertTrue($this->adapter->hasColumn('tzschema.tztable', 'time_notz'));

        $columns = $this->adapter->getColumns('tzschema.tztable');
        foreach ($columns as $column) {
            if (str_ends_with((string)$column->getName(), 'notz')) {
                $this->assertFalse($column->isTimezone(), 'column: ' . $column->getName());
            } else {
                $this->assertTrue($column->isTimezone(), 'column: ' . $column->getName());
            }
        }

        $this->adapter->dropSchema('tzschema');
    }

    public function testBulkInsertData(): void
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

    public function testBulkInsertBoolean(): void
    {
        $data = [
            [
                'column1' => true,
            ],
            [
                'column1' => false,
            ],
            [
                'column1' => null,
            ],
        ];
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'boolean', ['null' => true])
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertTrue($rows[0]['column1']);
        $this->assertFalse($rows[1]['column1']);
        $this->assertNull($rows[2]['column1']);
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
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
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

    public function testInsertBoolean(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'boolean', ['null' => true])
            ->addColumn('column2', 'text', ['null' => true])
            ->insert([
                [
                    'column1' => true,
                    'column2' => 'value',
                ],
                [
                    'column1' => false,
                ],
                [
                    'column1' => null,
                ],
            ])
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertTrue($rows[0]['column1']);
        $this->assertFalse($rows[1]['column1']);
        $this->assertNull($rows[2]['column1']);
    }

    public function testInsertDataWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        $table = new Table('schema1.table1', [], $this->adapter);
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

        $rows = $this->adapter->fetchAll('SELECT * FROM "schema1"."table1"');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);

        $this->adapter->dropSchema('schema1');
    }

    public function testTruncateTable(): void
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

    public function testTruncateTableWithSchema(): void
    {
        $this->adapter->createSchema('schema1');

        $table = new Table('schema1.table1', [], $this->adapter);
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

        $rows = $this->adapter->fetchAll('SELECT * FROM schema1.table1');
        $this->assertCount(2, $rows);
        $table->truncate();
        $rows = $this->adapter->fetchAll('SELECT * FROM schema1.table1');
        $this->assertCount(0, $rows);

        $this->adapter->dropSchema('schema1');
    }

    public function testDumpCreateTable(): void
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $table = new Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer', ['null' => true])
            ->addColumn('column3', 'string', ['default' => 'test', 'null' => false])
            ->save();

        $actualOutput = implode("\n", $this->out->messages());
        // Check for key parts of the CREATE TABLE statement
        // The identity column syntax varies between CakePHP/database versions
        $this->assertStringContainsString(
            'CREATE TABLE "public"."table1"',
            $actualOutput,
            'Passing the --dry-run option does not dump create table query',
        );
        $this->assertStringContainsString('"column1" VARCHAR DEFAULT NULL', $actualOutput);
        $this->assertStringContainsString('"column2" INT DEFAULT NULL', $actualOutput);
        $this->assertStringContainsString('"column3" VARCHAR NOT NULL DEFAULT \'test\'', $actualOutput);
        $this->assertStringContainsString('CONSTRAINT "table1_pkey" PRIMARY KEY ("id")', $actualOutput);
    }

    public function testDumpCreateTableWithSchema(): void
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $table = new Table('schema1.table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer', ['null' => true])
            ->addColumn('column3', 'string', ['default' => 'test', 'null' => false])
            ->save();

        $actualOutput = implode("\n", $this->out->messages());
        // Check for key parts of the CREATE TABLE statement
        // The identity column syntax varies between CakePHP/database versions
        $this->assertStringContainsString(
            'CREATE TABLE "schema1"."table1"',
            $actualOutput,
            'Passing the --dry-run option does not dump create table query',
        );
        $this->assertStringContainsString('"column1" VARCHAR DEFAULT NULL', $actualOutput);
        $this->assertStringContainsString('"column2" INT DEFAULT NULL', $actualOutput);
        $this->assertStringContainsString('"column3" VARCHAR NOT NULL DEFAULT \'test\'', $actualOutput);
        $this->assertStringContainsString('CONSTRAINT "table1_pkey" PRIMARY KEY ("id")', $actualOutput);
    }

    /**
     * Creates the table "table1".
     * Then enables dry run mode and inserts a record.
     * Asserts that output contains the insert statement and doesn't insert a record.
     */
    public function testDumpInsert(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

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
INSERT INTO "public"."table1" ("string_col") OVERRIDING SYSTEM VALUE VALUES ('test data');
INSERT INTO "public"."table1" ("string_col") OVERRIDING SYSTEM VALUE VALUES (null);
INSERT INTO "public"."table1" ("int_col") OVERRIDING SYSTEM VALUE VALUES (23);
OUTPUT;

        if (!$this->usingPostgres10()) {
            $expectedOutput = <<<'OUTPUT'
INSERT INTO "public"."table1" ("string_col") VALUES ('test data');
INSERT INTO "public"."table1" ("string_col") VALUES (null);
INSERT INTO "public"."table1" ("int_col") VALUES (23);
OUTPUT;
        }

        $actualOutput = implode("\n", $this->out->messages());
        $this->assertStringContainsString(
            $expectedOutput,
            $actualOutput,
            "Passing the --dry-run option doesn't dump the insert to the output",
        );

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll('assoc');
        $this->assertEquals(0, $res[0]['count']);
    }

    /**
     * Creates the table "table1".
     * Then enables dry run mode and inserts some records.
     * Asserts that output contains the insert statement and doesn't insert any record.
     */
    public function testDumpBulkinsert(): void
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

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
INSERT INTO "public"."table1" ("string_col", "int_col") OVERRIDING SYSTEM VALUE VALUES ('test_data1', 23), (null, 42);
OUTPUT;

        if (!$this->usingPostgres10()) {
            $expectedOutput = <<<'OUTPUT'
INSERT INTO "public"."table1" ("string_col", "int_col") VALUES ('test_data1', 23), (null, 42);
OUTPUT;
        }

        $actualOutput = implode("\n", $this->out->messages());
        $this->assertStringContainsString(
            $expectedOutput,
            $actualOutput,
            "Passing the --dry-run option doesn't dump the bulkinsert to the output",
        );

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll('assoc');
        $this->assertEquals(0, $res[0]['count']);
    }

    public function testDumpCreateTableAndThenInsert(): void
    {
        $options = $this->adapter->getOptions();
        $options['dryrun'] = true;
        $this->adapter->setOptions($options);

        $table = new Table('schema1.table1', ['id' => false, 'primary_key' => ['column1']], $this->adapter);
        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->save();

        $table = new Table('schema1.table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1,
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE "schema1"."table1" ("column1" VARCHAR NOT NULL, "column2" INT DEFAULT NULL, CONSTRAINT "table1_pkey" PRIMARY KEY ("column1"));
INSERT INTO "schema1"."table1" ("column1", "column2") OVERRIDING SYSTEM VALUE VALUES ('id1', 1);
OUTPUT;

        if (!$this->usingPostgres10()) {
            $expectedOutput = <<<'OUTPUT'
CREATE TABLE "schema1"."table1" ("column1" CHARACTER VARCHAR NOT NULL, "column2" INT DEFAULT NULL, CONSTRAINT "table1_pkey" PRIMARY KEY ("column1"));
INSERT INTO "schema1"."table1" ("column1", "column2") VALUES ('id1', 1);
OUTPUT;
        }

        $actualOutput = implode("\n", $this->out->messages());
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

    public function testRenameMixedCaseTableAndColumns(): void
    {
        $table = new Table('OrganizationSettings', [], $this->adapter);
        $table->addColumn('SettingType', 'string')
            ->create();

        $this->assertTrue($this->adapter->hasTable('OrganizationSettings'));
        $this->assertTrue($this->adapter->hasColumn('OrganizationSettings', 'id'));
        $this->assertTrue($this->adapter->hasColumn('OrganizationSettings', 'SettingType'));
        $this->assertFalse($this->adapter->hasColumn('OrganizationSettings', 'SettingTypeId'));

        $table = new Table('OrganizationSettings', [], $this->adapter);
        $table
            ->renameColumn('SettingType', 'SettingTypeId')
            ->update();

        $this->assertTrue($this->adapter->hasTable('OrganizationSettings'));
        $this->assertTrue($this->adapter->hasColumn('OrganizationSettings', 'id'));
        $this->assertTrue($this->adapter->hasColumn('OrganizationSettings', 'SettingTypeId'));
        $this->assertFalse($this->adapter->hasColumn('OrganizationSettings', 'SettingType'));
    }

    public static function serialProvider(): array
    {
        return [
            [AdapterInterface::TYPE_SMALLINTEGER],
            [AdapterInterface::TYPE_INTEGER],
            [AdapterInterface::TYPE_BIGINTEGER],
        ];
    }

    #[DataProvider('serialProvider')]
    public function testSerialAliases(string $columnType): void
    {
        $table = new Table('test', ['id' => false], $this->adapter);
        $table->addColumn('id', $columnType, ['identity' => true, 'generated' => null])->create();

        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $column = $columns[0];
        $this->assertSame($columnType, $column->getType());
        $this->assertTrue($column->isIdentity());
        $this->assertFalse($column->isNull());
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

        // PostgreSQL requires conflictColumns for insertOrUpdate
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PostgreSQL requires the $conflictColumns parameter');
        $table->insertOrUpdate([
            ['code' => 'USD', 'rate' => 1.0000],
        ], ['rate'], [])->save();
    }

    public function testAddSinglePartitionToExistingTable(): void
    {
        // Create a partitioned table with room to add more partitions
        $table = new Table('partitioned_orders', ['id' => false, 'primary_key' => ['id', 'order_date']], $this->adapter);
        $table->addColumn('id', 'integer')
            ->addColumn('order_date', 'date')
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->partitionBy(Partition::TYPE_RANGE, 'order_date')
            ->addPartition('p2022', ['from' => '2022-01-01', 'to' => '2023-01-01'])
            ->addPartition('p2023', ['from' => '2023-01-01', 'to' => '2024-01-01'])
            ->create();

        $this->assertTrue($this->adapter->hasTable('partitioned_orders'));

        // Add a new partition to the existing table
        $table = new Table('partitioned_orders', [], $this->adapter);
        $table->addPartitionToExisting('p2024', ['from' => '2024-01-01', 'to' => '2025-01-01'])
            ->save();

        // Verify the partition was added by inserting data that belongs in the new partition
        $this->adapter->execute(
            "INSERT INTO partitioned_orders (id, order_date, amount) VALUES (1, '2024-06-15', 100.00)",
        );

        $rows = $this->adapter->fetchAll("SELECT * FROM partitioned_orders WHERE order_date = '2024-06-15'");
        $this->assertCount(1, $rows);

        // Cleanup - drop partitioned table (CASCADE drops partitions)
        $this->adapter->dropTable('partitioned_orders');
    }

    public function testAddMultiplePartitionsToExistingTable(): void
    {
        // Create a partitioned table
        $table = new Table('partitioned_sales', ['id' => false, 'primary_key' => ['id', 'sale_date']], $this->adapter);
        $table->addColumn('id', 'integer')
            ->addColumn('sale_date', 'date')
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->partitionBy(Partition::TYPE_RANGE, 'sale_date')
            ->addPartition('p2022', ['from' => '2022-01-01', 'to' => '2023-01-01'])
            ->create();

        $this->assertTrue($this->adapter->hasTable('partitioned_sales'));

        // Add multiple partitions at once
        $table = new Table('partitioned_sales', [], $this->adapter);
        $table->addPartitionToExisting('p2023', ['from' => '2023-01-01', 'to' => '2024-01-01'])
            ->addPartitionToExisting('p2024', ['from' => '2024-01-01', 'to' => '2025-01-01'])
            ->addPartitionToExisting('p2025', ['from' => '2025-01-01', 'to' => '2026-01-01'])
            ->save();

        // Verify all partitions were added by inserting data into each
        $this->adapter->execute(
            "INSERT INTO partitioned_sales (id, sale_date, amount) VALUES (1, '2023-06-15', 100.00)",
        );
        $this->adapter->execute(
            "INSERT INTO partitioned_sales (id, sale_date, amount) VALUES (2, '2024-06-15', 200.00)",
        );
        $this->adapter->execute(
            "INSERT INTO partitioned_sales (id, sale_date, amount) VALUES (3, '2025-06-15', 300.00)",
        );

        $rows = $this->adapter->fetchAll('SELECT * FROM partitioned_sales');
        $this->assertCount(3, $rows);

        // Cleanup
        $this->adapter->dropTable('partitioned_sales');
    }

    public function testDropSinglePartitionFromExistingTable(): void
    {
        // Create a partitioned table with multiple partitions
        $table = new Table('partitioned_logs', ['id' => false, 'primary_key' => ['id']], $this->adapter);
        $table->addColumn('id', 'biginteger')
            ->addColumn('message', 'text')
            ->partitionBy(Partition::TYPE_RANGE, 'id')
            ->addPartition('p0', ['from' => 0, 'to' => 1000000])
            ->addPartition('p1', ['from' => 1000000, 'to' => 2000000])
            ->addPartition('p2', ['from' => 2000000, 'to' => 3000000])
            ->create();

        $this->assertTrue($this->adapter->hasTable('partitioned_logs'));

        // Insert data into partition p0
        $this->adapter->execute(
            "INSERT INTO partitioned_logs (id, message) VALUES (500, 'test message')",
        );

        // Drop the partition (this also removes the data in PostgreSQL)
        $table = new Table('partitioned_logs', [], $this->adapter);
        $table->dropPartition('p0')
            ->save();

        // Verify the partition table was dropped
        $this->assertFalse($this->adapter->hasTable('partitioned_logs_p0'));

        // Verify the main partitioned table still exists
        $this->assertTrue($this->adapter->hasTable('partitioned_logs'));

        // Verify the table still works by inserting into the next partition
        $this->adapter->execute(
            "INSERT INTO partitioned_logs (id, message) VALUES (1500000, 'another message')",
        );

        $rows = $this->adapter->fetchAll('SELECT * FROM partitioned_logs WHERE id = 1500000');
        $this->assertCount(1, $rows);

        // Cleanup - drop partitioned table (CASCADE drops remaining partitions)
        $this->adapter->dropTable('partitioned_logs');
    }

    public function testDropMultiplePartitionsFromExistingTable(): void
    {
        // Create a partitioned table with multiple partitions
        $table = new Table('partitioned_archive', ['id' => false, 'primary_key' => ['id']], $this->adapter);
        $table->addColumn('id', 'biginteger')
            ->addColumn('data', 'text')
            ->partitionBy(Partition::TYPE_RANGE, 'id')
            ->addPartition('p0', ['from' => 0, 'to' => 1000000])
            ->addPartition('p1', ['from' => 1000000, 'to' => 2000000])
            ->addPartition('p2', ['from' => 2000000, 'to' => 3000000])
            ->addPartition('p3', ['from' => 3000000, 'to' => 4000000])
            ->create();

        $this->assertTrue($this->adapter->hasTable('partitioned_archive'));

        // Insert data into partitions
        $this->adapter->execute(
            "INSERT INTO partitioned_archive (id, data) VALUES (500, 'data in p0')",
        );
        $this->adapter->execute(
            "INSERT INTO partitioned_archive (id, data) VALUES (1500000, 'data in p1')",
        );
        $this->adapter->execute(
            "INSERT INTO partitioned_archive (id, data) VALUES (2500000, 'data in p2')",
        );

        // Drop multiple partitions at once
        $table = new Table('partitioned_archive', [], $this->adapter);
        $table->dropPartition('p0')
            ->dropPartition('p1')
            ->save();

        // Verify the partition tables were dropped
        $this->assertFalse($this->adapter->hasTable('partitioned_archive_p0'));
        $this->assertFalse($this->adapter->hasTable('partitioned_archive_p1'));

        // Verify data in p2 still exists
        $rows = $this->adapter->fetchAll('SELECT * FROM partitioned_archive WHERE id = 2500000');
        $this->assertCount(1, $rows);

        // Cleanup
        $this->adapter->dropTable('partitioned_archive');
    }
}
