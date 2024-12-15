<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Util;

use Cake\Utility\Inflector;
use DateTime;
use DateTimeZone;
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
     * @psalm-var non-empty-string
     */
    protected const MIGRATION_FILE_NAME_PATTERN = '/^\d+_([a-z][a-z\d]*(?:_[a-z\d]+)*)\.php$/i';

    /**
     * @var string
     * @psalm-var non-empty-string
     */
    protected const MIGRATION_FILE_NAME_NO_NAME_PATTERN = '/^[0-9]{14}\.php$/';

    /**
     * @var string
     * @psalm-var non-empty-string
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
        preg_match('/^[0-9]+/', basename($fileName), $matches);
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
        // TODO it would be nice to replace this with Inflector::underscore
        // but it will break compatibility for little end user gain.
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
            || (bool)preg_match(static::MIGRATION_FILE_NAME_NO_NAME_PATTERN, $fileName);
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
        $table = 'phinxlog';
        if ($plugin) {
            $prefix = Inflector::underscore($plugin) . '_';
            $prefix = str_replace(['\\', '/', '.'], '_', $prefix);
            $table = $prefix . $table;
        }

        return $table;
    }
}
