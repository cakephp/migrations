<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Command;

use Cake\Console\BaseCommand;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\TestSuite\StringCompareTrait;
use Migrations\Command\BakeMigrationCommand;
use Migrations\Test\TestCase\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * BakeMigrationCommandTest class
 */
class BakeMigrationCommandTest extends TestCase
{
    use StringCompareTrait;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $createUsers = glob(ROOT . DS . 'config' . DS . 'Migrations' . DS . '*_*Users.php');
        if ($createUsers) {
            foreach ($createUsers as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Test empty migration.
     *
     * @return void
     */
    public function testNoContents()
    {
        $this->exec('bake migration NoContents --connection test');

        $file = glob(ROOT . DS . 'config' . DS . 'Migrations' . DS . '*_NoContents.php');
        $this->generatedFile = current($file);

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($this->generatedFile);
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * data provider for testCreate
     *
     * @return array
     */
    public static function nameVariations()
    {
        return [
            ['name', '.php'],
            ['name created modified', 'Datetime.php'],
            ['id:integer:primary_key name created modified', 'PrimaryKey.php'],
            ['id:uuid:primary_key name created modified', 'PrimaryKeyUuid.php'],
            ['name:string[128] counter:integer[8]', 'FieldLength.php'],
        ];
    }

    /**
     * Test the execute method.
     *
     * @return void
     */
    #[DataProvider('nameVariations')]
    public function testCreate($name, $fileSuffix)
    {
        $this->exec("bake migration CreateUsers  {$name} --connection test");

        $file = glob(ROOT . DS . 'config' . DS . 'Migrations' . DS . '*_CreateUsers.php');
        $filePath = current($file);

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($filePath);
        $this->assertSameAsFile(__FUNCTION__ . $fileSuffix, $result);
    }

    /**
     * Test that when the phinx backend is active migrations use
     * phinx base classes.
     */
    public function testCreatePhinx()
    {
        Configure::write('Migrations.backend', 'phinx');
        $this->exec('bake migration CreateUsers  name --connection test');

        $file = glob(ROOT . DS . 'config' . DS . 'Migrations' . DS . '*_CreateUsers.php');
        $filePath = current($file);

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($filePath);
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * Tests that baking a migration with the name as another will throw an exception.
     */
    public function testCreateDuplicateName()
    {
        $this->exec('bake migration CreateUsers --connection test');
        $this->exec('bake migration CreateUsers --connection test');
        $this->assertExitCode(BaseCommand::CODE_ERROR);
        $this->assertErrorContains('A migration with the name `CreateUsers` already exists. Please use a different name.');
    }

    public function testCreateBuiltinAlias()
    {
        Configure::write('Migrations.backend', 'builtin');
        $this->exec('migrations create CreateUsers --connection test');
        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $this->assertOutputRegExp('/Wrote.*?CreateUsers\.php/');
    }

    /**
     * Tests that baking a migration with the "drop" string inside the name generates a valid drop migration.
     */
    public function testCreateDropMigration()
    {
        $this->exec('bake migration DropUsers --connection test');
        $this->assertOutputRegExp('/Wrote.*?DropUsers\.php/');

        $file = glob(ROOT . DS . 'config' . DS . 'Migrations' . DS . '*_DropUsers.php');
        $filePath = current($file);

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($filePath);
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * Tests that baking a migration with the name as another with the parameter "force", will delete the existing file.
     */
    public function testCreateDuplicateNameWithForce()
    {
        $this->exec('bake migration CreateUsers --connection test --force');

        $file = glob(ROOT . DS . 'config' . DS . 'Migrations' . DS . '*_CreateUsers.php');
        $filePath = current($file);
        sleep(1);

        $this->exec('bake migration CreateUsers --connection test --force');
        $file = glob(ROOT . DS . 'config' . DS . 'Migrations' . DS . '*_CreateUsers.php');
        $this->assertNotEquals($filePath, current($file));
    }

    /**
     * Test that adding a field or altering a table with a primary
     * key will error out
     *
     * @return void
     */
    public function testAddPrimaryKeyToExistingTable()
    {
        $this->exec('bake migration AddPkToUsers somefield:primary_key --connection test');

        $this->assertExitCode(BaseCommand::CODE_ERROR);
        $this->assertErrorContains('Adding a primary key to an already existing table is not supported.');
    }

    /**
     * Test that adding a field or altering a table with a primary
     * key will error out
     *
     * @return void
     */
    public function testAddPrimaryKeyToExistingUsersTable()
    {
        $this->exec('bake migration AlterUsers somefield:primary_key --connection test');

        $this->assertExitCode(BaseCommand::CODE_ERROR);
        $this->assertErrorContains('Adding a primary key to an already existing table is not supported.');
    }

    /**
     * @return void
     */
    public function testDetectAction()
    {
        $command = new BakeMigrationCommand();
        $this->assertEquals(
            ['create_table', 'groups'],
            $command->detectAction('CreateGroups')
        );
        $this->assertEquals(
            ['create_table', 'users'],
            $command->detectAction('CreateUsers')
        );
        $this->assertEquals(
            ['create_table', 'groups_users'],
            $command->detectAction('CreateGroupsUsers')
        );
        $this->assertEquals(
            ['create_table', 'articles_i18n'],
            $command->detectAction('CreateArticlesI18n')
        );

        $this->assertEquals(
            ['drop_table', 'groups'],
            $command->detectAction('DropGroups')
        );
        $this->assertEquals(
            ['drop_table', 'users'],
            $command->detectAction('DropUsers')
        );
        $this->assertEquals(
            ['drop_table', 'groups_users'],
            $command->detectAction('DropGroupsUsers')
        );
        $this->assertEquals(
            ['drop_table', 'articles_i18n'],
            $command->detectAction('DropArticlesI18n')
        );

        $this->assertEquals(
            ['add_field', 'groups'],
            $command->detectAction('AddFieldToGroups')
        );
        $this->assertEquals(
            ['add_field', 'users'],
            $command->detectAction('AddFieldToUsers')
        );
        $this->assertEquals(
            ['add_field', 'groups_users'],
            $command->detectAction('AddFieldToGroupsUsers')
        );
        $this->assertEquals(
            ['add_field', 'groups'],
            $command->detectAction('AddThingToGroups')
        );
        $this->assertEquals(
            ['add_field', 'groups'],
            $command->detectAction('AddTokenToGroups')
        );
        $this->assertEquals(
            ['add_field', 'users'],
            $command->detectAction('AddAnotherFieldToUsers')
        );
        $this->assertEquals(
            ['add_field', 'groups_users'],
            $command->detectAction('AddSomeFieldToGroupsUsers')
        );
        $this->assertEquals(
            ['add_field', 'todos'],
            $command->detectAction('AddSomeFieldToTodos')
        );
        $this->assertEquals(
            ['add_field', 'articles_i18n'],
            $command->detectAction('AddSomeFieldToArticlesI18n')
        );

        $this->assertEquals(
            ['drop_field', 'groups'],
            $command->detectAction('RemoveFieldsFromGroups')
        );
        $this->assertEquals(
            ['drop_field', 'users'],
            $command->detectAction('RemoveFieldsFromUsers')
        );
        $this->assertEquals(
            ['drop_field', 'groups_users'],
            $command->detectAction('RemoveFieldsFromGroupsUsers')
        );
        $this->assertEquals(
            ['drop_field', 'groups'],
            $command->detectAction('RemoveThingFromGroups')
        );
        $this->assertEquals(
            ['drop_field', 'users'],
            $command->detectAction('RemoveAnotherFieldFromUsers')
        );
        $this->assertEquals(
            ['drop_field', 'groups_users'],
            $command->detectAction('RemoveSomeFieldFromGroupsUsers')
        );
        $this->assertEquals(
            ['drop_field', 'fromages'],
            $command->detectAction('RemoveSomeFieldFromFromages')
        );
        $this->assertEquals(
            ['drop_field', 'fromages'],
            $command->detectAction('RemoveFromageFromFromages')
        );
        $this->assertEquals(
            ['drop_field', 'articles_i18n'],
            $command->detectAction('RemoveFromageFromArticlesI18n')
        );

        $this->assertEquals(
            ['alter_table', 'groups'],
            $command->detectAction('AlterGroups')
        );
        $this->assertEquals(
            ['alter_table', 'users'],
            $command->detectAction('AlterUsers')
        );
        $this->assertEquals(
            ['alter_table', 'groups_users'],
            $command->detectAction('AlterGroupsUsers')
        );
        $this->assertEquals(
            ['alter_table', 'articles_i18n'],
            $command->detectAction('AlterArticlesI18n')
        );

        $this->assertSame(
            [],
            $command->detectAction('ReaddColumnsToTable')
        );

        $this->assertEquals(
            ['alter_field', 'groups'],
            $command->detectAction('AlterFieldOnGroups')
        );
        $this->assertEquals(
            ['alter_field', 'users'],
            $command->detectAction('AlterFieldOnUsers')
        );
        $this->assertEquals(
            ['alter_field', 'groups_users'],
            $command->detectAction('AlterFieldOnGroupsUsers')
        );
        $this->assertEquals(
            ['alter_field', 'todos'],
            $command->detectAction('AlterFieldOnTodos')
        );
        $this->assertEquals(
            ['alter_field', 'articles_i18n'],
            $command->detectAction('AlterFieldOnArticlesI18n')
        );
    }

    /**
     * Test migration without Create, Drop, Add, Remove, Alter prefix
     *
     * @return void
     */
    public function testActionWithoutValidPrefix()
    {
        $this->exec('bake migration SleepUsers name:string --connection test');

        $this->assertExitCode(BaseCommand::CODE_ERROR);
        $this->assertErrorContains('When applying fields the migration name should start with one of the following prefixes: `Create`, `Drop`, `Add`, `Remove`, `Alter`.');
    }
}
