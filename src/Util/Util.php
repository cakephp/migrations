<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Util;

use Cake\Core\Configure;
use Cake\Utility\Inflector;
use DateTime;
use DateTimeZone;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;
use RuntimeException;

/**
 * Temporary compatibility shim that can be refactored away.
 *
 * @internal
 */
class Util
{
    /**
     * @var string
     */
    public const DATE_FORMAT = 'YmdHis';

    /**
     * @var string
     * @phpstan-var non-empty-string
     */
    protected const MIGRATION_FILE_NAME_PATTERN = '/^\d+_([a-z][a-z\d]*(?:_[a-z\d]+)*)\.php$/i';

    /**
     * @var string
     * @phpstan-var non-empty-string
     */
    protected const MIGRATION_FILE_NAME_NO_NAME_PATTERN = '/^[0-9]{14}\.php$/';

    /**
     * Enhanced migration file name pattern with readable timestamp and CamelCase
     * Example: 2024_12_08_120000_CreateUsersTable.php
     *
     * @var string
     * @phpstan-var non-empty-string
     */
    protected const READABLE_MIGRATION_FILE_NAME_PATTERN = '/^(\d{4})_(\d{2})_(\d{2})_(\d{6})_([A-Z][a-zA-Z\d]*)\.php$/';

    /**
     * @var string
     * @phpstan-var non-empty-string
     */
    protected const SEED_FILE_NAME_PATTERN = '/^([a-z][a-z\d]*)\.php$/i';

    /**
     * Gets the current timestamp string, in UTC.
     *
     * @return string
     */
    public static function getCurrentTimestamp(?int $offset = null): string
    {
        $time = 'now';
        if ($offset) {
            $time = '+' . $offset . ' seconds';
        }
        $dt = new DateTime($time, new DateTimeZone('UTC'));

        return $dt->format(static::DATE_FORMAT);
    }

    /**
     * Gets an array of all the existing migration class names.
     *
     * @param string $path Path
     * @return string[]
     */
    public static function getExistingMigrationClassNames(string $path): array
    {
        $classNames = [];

        if (!is_dir($path)) {
            return $classNames;
        }

        // filter the files to only get the ones that match our naming scheme
        $phpFiles = static::getFiles($path);

        foreach ($phpFiles as $filePath) {
            $fileName = basename($filePath);
            if ($fileName && preg_match(static::MIGRATION_FILE_NAME_PATTERN, $fileName)) {
                $classNames[] = static::mapFileNameToClassName($fileName);
            }
        }

        return $classNames;
    }

    /**
     * Get the version from the beginning of a file name.
     *
     * @param string $fileName File Name
     * @return int
     */
    public static function getVersionFromFileName(string $fileName): int
    {
        $matches = [];
        $baseName = basename($fileName);

        // Check for readable format: 2024_12_08_120000_CreateUsersTable.php
        if (preg_match(static::READABLE_MIGRATION_FILE_NAME_PATTERN, $baseName, $matches)) {
            // Convert to traditional format: 20241208120000
            return (int)($matches[1] . $matches[2] . $matches[3] . $matches[4]);
        }

        // Traditional format
        preg_match('/^[0-9]+/', $baseName, $matches);
        $value = (int)($matches[0] ?? null);
        if (!$value) {
            throw new RuntimeException(sprintf('Cannot get a valid version from filename `%s`', $fileName));
        }

        return $value;
    }

    /**
     * Turn migration names like 'CreateUserTable' into file names like
     * '12345678901234_create_user_table.php' or 'LimitResourceNamesTo30Chars' into
     * '12345678901234_limit_resource_names_to_30_chars.php'.
     *
     * @param string $className Class Name
     * @return string
     */
    public static function mapClassNameToFileName(string $className): string
    {
        $snake = function ($matches) {
            return '_' . strtolower($matches[0]);
        };
        $fileName = preg_replace_callback('/\d+|[A-Z]/', $snake, $className);
        $fileName = static::getCurrentTimestamp() . "$fileName.php";

        return $fileName;
    }

    /**
     * Turn file names like '12345678901234_create_user_table.php' into class
     * names like 'CreateUserTable'.
     *
     * @param string $fileName File Name
     * @return string
     */
    public static function mapFileNameToClassName(string $fileName): string
    {
        $matches = [];

        // Check for readable format first: 2024_12_08_120000_CreateUsersTable.php
        if (preg_match(static::READABLE_MIGRATION_FILE_NAME_PATTERN, $fileName, $matches)) {
            return $matches[5]; // Return the CamelCase class name directly
        }

        if (preg_match(static::MIGRATION_FILE_NAME_PATTERN, $fileName, $matches)) {
            $fileName = $matches[1];
        } elseif (preg_match(static::MIGRATION_FILE_NAME_NO_NAME_PATTERN, $fileName)) {
            return 'V' . substr($fileName, 0, strlen($fileName) - 4);
        }

        return Inflector::camelize($fileName);
    }

    /**
     * Check if a migration file name is valid.
     *
     * @param string $fileName File Name
     * @return bool
     */
    public static function isValidMigrationFileName(string $fileName): bool
    {
        return (bool)preg_match(static::MIGRATION_FILE_NAME_PATTERN, $fileName)
            || (bool)preg_match(static::MIGRATION_FILE_NAME_NO_NAME_PATTERN, $fileName)
            || (bool)preg_match(static::READABLE_MIGRATION_FILE_NAME_PATTERN, $fileName);
    }

    /**
     * Check if a seed file name is valid.
     *
     * @param string $fileName File Name
     * @return bool
     */
    public static function isValidSeedFileName(string $fileName): bool
    {
        return (bool)preg_match(static::SEED_FILE_NAME_PATTERN, $fileName);
    }

    /**
     * Get a human-readable display name for a seed class.
     *
     * Strips the 'Seed' suffix from class names like 'UsersSeed' to produce 'Users'.
     *
     * @param string $seedName The seed class name
     * @return string The display name without the 'Seed' suffix
     */
    public static function getSeedDisplayName(string $seedName): string
    {
        if (str_ends_with($seedName, 'Seed')) {
            return substr($seedName, 0, -4);
        }

        return $seedName;
    }

    /**
     * Expands a set of paths with curly braces (if supported by the OS).
     *
     * @param string[] $paths Paths
     * @return array<array-key, string>
     */
    public static function globAll(array $paths): array
    {
        $result = [];

        foreach ($paths as $path) {
            $result = array_merge($result, static::glob($path));
        }

        return $result;
    }

    /**
     * Expands a path with curly braces (if supported by the OS).
     *
     * @param string $path Path
     * @return string[]
     */
    public static function glob(string $path): array
    {
        $result = glob($path, defined('GLOB_BRACE') ? GLOB_BRACE : 0);
        if ($result) {
            return $result;
        }

        return [];
    }

    /**
     * Given an array of paths, return all unique PHP files that are in them
     *
     * @param string|string[] $paths Path or array of paths to get .php files.
     * @return string[]
     */
    public static function getFiles(string|array $paths): array
    {
        $files = static::globAll(array_map(function ($path) {
            return $path . DIRECTORY_SEPARATOR . '*.php';
        }, (array)$paths));
        // glob() can return the same file multiple times
        // This will cause the migration to fail with a
        // false assumption of duplicate migrations
        // https://php.net/manual/en/function.glob.php#110340
        $files = array_unique($files);

        return $files;
    }

    /**
     * @param string|null $plugin
     * @return string
     */
    public static function tableName(?string $plugin): string
    {
        // When using unified table, always return the same table name
        if (Configure::read('Migrations.legacyTables') === false) {
            return UnifiedMigrationsTableStorage::TABLE_NAME;
        }

        $table = 'phinxlog';
        if ($plugin) {
            $prefix = Inflector::underscore($plugin) . '_';
            $prefix = str_replace(['\\', '/', '.'], '_', $prefix);
            $table = $prefix . $table;
        }

        return $table;
    }
}
