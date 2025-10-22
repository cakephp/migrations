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

use Cake\Database\Schema\TableSchema;
use Migrations\Command\BakeMigrationDiffCommand;
use Migrations\Test\TestCase\TestCase;
use ReflectionClass;

/**
 * Unit tests for BakeMigrationDiffCommand
 */
class BakeMigrationDiffCommandUnitTest extends TestCase
{
    /**
     * Test that decimal column changes generate correct precision and scale
     *
     * This tests the fix for https://github.com/cakephp/migrations/issues/659
     *
     * @return void
     */
    public function testDecimalColumnChangesUsePrecisionAndScale(): void
    {
        // Create mock schemas
        $oldSchema = new TableSchema('products');
        $oldSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $oldSchema->addColumn('price', [
            'type' => 'decimal',
            'length' => 4,
            'precision' => 2,
            'null' => false,
            'default' => null,
        ]);
        $oldSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        $currentSchema = new TableSchema('products');
        $currentSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $currentSchema->addColumn('price', [
            'type' => 'decimal',
            'length' => 6, // Changed from 4 to 6
            'precision' => 2,
            'null' => false,
            'default' => null,
        ]);
        $currentSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        // Set up the command
        $command = new BakeMigrationDiffCommand();

        // Use reflection to set protected properties
        $reflection = new ReflectionClass($command);

        $dumpSchemaProperty = $reflection->getProperty('dumpSchema');
        $dumpSchemaProperty->setAccessible(true);
        $dumpSchemaProperty->setValue($command, ['products' => $oldSchema]);

        $currentSchemaProperty = $reflection->getProperty('currentSchema');
        $currentSchemaProperty->setAccessible(true);
        $currentSchemaProperty->setValue($command, ['products' => $currentSchema]);

        $commonTablesProperty = $reflection->getProperty('commonTables');
        $commonTablesProperty->setAccessible(true);
        $commonTablesProperty->setValue($command, ['products' => $currentSchema]);

        $templateDataProperty = $reflection->getProperty('templateData');
        $templateDataProperty->setAccessible(true);
        $templateDataProperty->setValue($command, []);

        // Call the protected getColumns method
        $getColumnsMethod = $reflection->getMethod('getColumns');
        $getColumnsMethod->setAccessible(true);
        $getColumnsMethod->invoke($command);

        // Get the template data
        $templateData = $templateDataProperty->getValue($command);

        // Assert that the decimal column change has precision and scale, not limit
        $this->assertArrayHasKey('products', $templateData);
        $this->assertArrayHasKey('columns', $templateData['products']);
        $this->assertArrayHasKey('changed', $templateData['products']['columns']);
        $this->assertArrayHasKey('price', $templateData['products']['columns']['changed']);

        $priceChanges = $templateData['products']['columns']['changed']['price'];

        // Should have precision and scale
        $this->assertArrayHasKey('precision', $priceChanges, 'Decimal column should have precision');
        $this->assertArrayHasKey('scale', $priceChanges, 'Decimal column should have scale');
        $this->assertEquals(6, $priceChanges['precision'], 'Precision should be 6 (the total digits)');
        $this->assertEquals(2, $priceChanges['scale'], 'Scale should be 2 (the decimal places)');

        // Should NOT have length or limit
        $this->assertArrayNotHasKey('length', $priceChanges, 'Decimal column should not have length');
        $this->assertArrayNotHasKey('limit', $priceChanges, 'Decimal column should not have limit');
    }

    /**
     * Test that non-decimal column changes use limit (not precision/scale)
     *
     * @return void
     */
    public function testNonDecimalColumnChangesUseLimit(): void
    {
        // Create mock schemas
        $oldSchema = new TableSchema('products');
        $oldSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $oldSchema->addColumn('name', [
            'type' => 'string',
            'length' => 100,
            'null' => false,
            'default' => null,
        ]);
        $oldSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        $currentSchema = new TableSchema('products');
        $currentSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $currentSchema->addColumn('name', [
            'type' => 'string',
            'length' => 255, // Changed from 100 to 255
            'null' => false,
            'default' => null,
        ]);
        $currentSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        // Set up the command
        $command = new BakeMigrationDiffCommand();

        // Use reflection to set protected properties
        $reflection = new ReflectionClass($command);

        $dumpSchemaProperty = $reflection->getProperty('dumpSchema');
        $dumpSchemaProperty->setAccessible(true);
        $dumpSchemaProperty->setValue($command, ['products' => $oldSchema]);

        $currentSchemaProperty = $reflection->getProperty('currentSchema');
        $currentSchemaProperty->setAccessible(true);
        $currentSchemaProperty->setValue($command, ['products' => $currentSchema]);

        $commonTablesProperty = $reflection->getProperty('commonTables');
        $commonTablesProperty->setAccessible(true);
        $commonTablesProperty->setValue($command, ['products' => $currentSchema]);

        $templateDataProperty = $reflection->getProperty('templateData');
        $templateDataProperty->setAccessible(true);
        $templateDataProperty->setValue($command, []);

        // Call the protected getColumns method
        $getColumnsMethod = $reflection->getMethod('getColumns');
        $getColumnsMethod->setAccessible(true);
        $getColumnsMethod->invoke($command);

        // Get the template data
        $templateData = $templateDataProperty->getValue($command);

        // Assert that the string column change has limit, not precision/scale
        $this->assertArrayHasKey('products', $templateData);
        $this->assertArrayHasKey('columns', $templateData['products']);
        $this->assertArrayHasKey('changed', $templateData['products']['columns']);
        $this->assertArrayHasKey('name', $templateData['products']['columns']['changed']);

        $nameChanges = $templateData['products']['columns']['changed']['name'];

        // Should have limit
        $this->assertArrayHasKey('limit', $nameChanges, 'String column should have limit');
        $this->assertEquals(255, $nameChanges['limit'], 'Limit should be 255');

        // Should NOT have length, precision, or scale
        $this->assertArrayNotHasKey('length', $nameChanges, 'String column should not have length');
        $this->assertArrayNotHasKey('precision', $nameChanges, 'String column should not have precision');
        $this->assertArrayNotHasKey('scale', $nameChanges, 'String column should not have scale');
    }

    /**
     * Test that decimal columns with only scale change are handled correctly
     *
     * @return void
     */
    public function testDecimalColumnScaleChangeOnly(): void
    {
        // Create mock schemas
        $oldSchema = new TableSchema('products');
        $oldSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $oldSchema->addColumn('price', [
            'type' => 'decimal',
            'length' => 6,
            'precision' => 2,
            'null' => false,
            'default' => null,
        ]);
        $oldSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        $currentSchema = new TableSchema('products');
        $currentSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $currentSchema->addColumn('price', [
            'type' => 'decimal',
            'length' => 6, // Same
            'precision' => 3, // Changed from 2 to 3
            'null' => false,
            'default' => null,
        ]);
        $currentSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        // Set up the command
        $command = new BakeMigrationDiffCommand();

        // Use reflection to set protected properties
        $reflection = new ReflectionClass($command);

        $dumpSchemaProperty = $reflection->getProperty('dumpSchema');
        $dumpSchemaProperty->setAccessible(true);
        $dumpSchemaProperty->setValue($command, ['products' => $oldSchema]);

        $currentSchemaProperty = $reflection->getProperty('currentSchema');
        $currentSchemaProperty->setAccessible(true);
        $currentSchemaProperty->setValue($command, ['products' => $currentSchema]);

        $commonTablesProperty = $reflection->getProperty('commonTables');
        $commonTablesProperty->setAccessible(true);
        $commonTablesProperty->setValue($command, ['products' => $currentSchema]);

        $templateDataProperty = $reflection->getProperty('templateData');
        $templateDataProperty->setAccessible(true);
        $templateDataProperty->setValue($command, []);

        // Call the protected getColumns method
        $getColumnsMethod = $reflection->getMethod('getColumns');
        $getColumnsMethod->setAccessible(true);
        $getColumnsMethod->invoke($command);

        // Get the template data
        $templateData = $templateDataProperty->getValue($command);

        // Assert that the decimal column change has precision and scale
        $this->assertArrayHasKey('products', $templateData);
        $this->assertArrayHasKey('columns', $templateData['products']);
        $this->assertArrayHasKey('changed', $templateData['products']['columns']);
        $this->assertArrayHasKey('price', $templateData['products']['columns']['changed']);

        $priceChanges = $templateData['products']['columns']['changed']['price'];

        // Should have precision (same as before) and scale (new value)
        $this->assertArrayHasKey('precision', $priceChanges, 'Decimal column should have precision');
        $this->assertArrayHasKey('scale', $priceChanges, 'Decimal column should have scale');
        $this->assertEquals(6, $priceChanges['precision'], 'Precision should be 6');
        $this->assertEquals(3, $priceChanges['scale'], 'Scale should be 3 (changed)');
    }

    /**
     * Test that decimal columns with both precision and scale changing are handled correctly
     *
     * This tests the edge case where both values change together
     *
     * @return void
     */
    public function testDecimalColumnBothPrecisionAndScaleChange(): void
    {
        // Create mock schemas
        $oldSchema = new TableSchema('products');
        $oldSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $oldSchema->addColumn('price', [
            'type' => 'decimal',
            'length' => 4,
            'precision' => 2,
            'null' => false,
            'default' => null,
        ]);
        $oldSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        $currentSchema = new TableSchema('products');
        $currentSchema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true]);
        $currentSchema->addColumn('price', [
            'type' => 'decimal',
            'length' => 6, // Changed from 4 to 6
            'precision' => 3, // Changed from 2 to 3
            'null' => false,
            'default' => null,
        ]);
        $currentSchema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        // Set up the command
        $command = new BakeMigrationDiffCommand();

        // Use reflection to set protected properties
        $reflection = new ReflectionClass($command);

        $dumpSchemaProperty = $reflection->getProperty('dumpSchema');
        $dumpSchemaProperty->setAccessible(true);
        $dumpSchemaProperty->setValue($command, ['products' => $oldSchema]);

        $currentSchemaProperty = $reflection->getProperty('currentSchema');
        $currentSchemaProperty->setAccessible(true);
        $currentSchemaProperty->setValue($command, ['products' => $currentSchema]);

        $commonTablesProperty = $reflection->getProperty('commonTables');
        $commonTablesProperty->setAccessible(true);
        $commonTablesProperty->setValue($command, ['products' => $currentSchema]);

        $templateDataProperty = $reflection->getProperty('templateData');
        $templateDataProperty->setAccessible(true);
        $templateDataProperty->setValue($command, []);

        // Call the protected getColumns method
        $getColumnsMethod = $reflection->getMethod('getColumns');
        $getColumnsMethod->setAccessible(true);
        $getColumnsMethod->invoke($command);

        // Get the template data
        $templateData = $templateDataProperty->getValue($command);

        // Assert that the decimal column change has both precision and scale updated
        $this->assertArrayHasKey('products', $templateData);
        $this->assertArrayHasKey('columns', $templateData['products']);
        $this->assertArrayHasKey('changed', $templateData['products']['columns']);
        $this->assertArrayHasKey('price', $templateData['products']['columns']['changed']);

        $priceChanges = $templateData['products']['columns']['changed']['price'];

        // Should have both precision and scale with new values
        $this->assertArrayHasKey('precision', $priceChanges, 'Decimal column should have precision');
        $this->assertArrayHasKey('scale', $priceChanges, 'Decimal column should have scale');
        $this->assertEquals(6, $priceChanges['precision'], 'Precision should be 6 (changed from 4)');
        $this->assertEquals(3, $priceChanges['scale'], 'Scale should be 3 (changed from 2)');

        // Should NOT have length or limit
        $this->assertArrayNotHasKey('length', $priceChanges, 'Decimal column should not have length');
        $this->assertArrayNotHasKey('limit', $priceChanges, 'Decimal column should not have limit');
    }
}
