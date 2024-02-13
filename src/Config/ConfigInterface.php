<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Config;

use ArrayAccess;
use Psr\Container\ContainerInterface;

/**
 * Phinx configuration interface.
 *
 * @template-implemements ArrayAccess<string>
 */
interface ConfigInterface extends ArrayAccess
{
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
     * Gets the paths to search for migration files.
     *
     * @return string[]
     */
    public function getMigrationPaths(): array;

    /**
     * Gets the paths to search for seed files.
     *
     * @return string[]
     */
    public function getSeedPaths(): array;

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
     * Get the user-provided container for instantiating seeds
     *
     * @return \Psr\Container\ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface;

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
