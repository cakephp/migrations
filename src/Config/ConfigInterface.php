<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Config;

use ArrayAccess;

/**
 * Phinx configuration interface.
 *
 * @template-implemements ArrayAccess<string>
 */
interface ConfigInterface extends ArrayAccess
{
    public const DEFAULT_MIGRATION_FOLDER = 'Migrations';
    public const DEFAULT_SEED_FOLDER = 'Seeds';

    /**
     * Returns the configuration for the current environment.
     *
     * This method returns <code>null</code> if the specified environment
     * doesn't exist.
     *
     * @return array|null
     */
    public function getEnvironment(): ?array;

    /**
     * Gets the path to search for migration files.
     *
     * @return string
     */
    public function getMigrationPath(): string;

    /**
     * Gets the path to search for seed files.
     *
     * @return string
     */
    public function getSeedPath(): string;

    /**
     * Get the connection namee
     *
     * @return string|false
     */
    public function getConnection(): string|false;

    /**
     * Get the template file name.
     *
     * @return string|false
     */
    public function getTemplateFile(): string|false;

    /**
     * Get the template class name.
     *
     * @return string|false
     */
    public function getTemplateClass(): string|false;

    /**
     * Get the template style to use, either change or up_down.
     *
     * @return string
     */
    public function getTemplateStyle(): string;

    /**
     * Get the version order.
     *
     * @return string
     */
    public function getVersionOrder(): string;

    /**
     * Is version order creation time?
     *
     * @return bool
     */
    public function isVersionOrderCreationTime(): bool;

    /**
     * Gets the base class name for migrations.
     *
     * @param bool $dropNamespace Return the base migration class name without the namespace.
     * @return string
     */
    public function getMigrationBaseClassName(bool $dropNamespace = true): string;

    /**
     * Gets the base class name for seeders.
     *
     * @param bool $dropNamespace Return the base seeder class name without the namespace.
     * @return string
     */
    public function getSeedBaseClassName(bool $dropNamespace = true): string;

    /**
     * Get the seeder template file name or null if not set.
     *
     * @return string|null
     */
    public function getSeedTemplateFile(): ?string;
}
