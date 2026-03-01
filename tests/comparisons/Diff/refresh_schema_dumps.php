<?php
declare(strict_types=1);

/**
 * # Check which Diff lock fixtures need refresh
 * php tests/comparisons/Diff/refresh_schema_dumps.php
 *
 * # Regenerate all Diff lock fixtures in place
 * php tests/comparisons/Diff/refresh_schema_dumps.php --write
 *
 * # Regenerate only one fixture
 * php tests/comparisons/Diff/refresh_schema_dumps.php --write --file tests/comparisons/Diff/decimalChange/schema-dump-test_comparisons_mysql.lock
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

const EXIT_OK = 0;
const EXIT_UPDATES_AVAILABLE = 1;
const EXIT_ERROR = 2;

/**
 * Defaults for known typed properties that can be introduced between CakePHP releases.
 *
 * @var array<string, array<string, mixed>>
 */
$knownDefaults = [
    Cake\Database\Schema\Column::class => [
        'fixed' => false,
    ],
];

$options = getopt('', ['write', 'file:']);
$write = isset($options['write']);
$filesOption = $options['file'] ?? [];
$selectedFiles = is_array($filesOption) ? $filesOption : [$filesOption];

$files = getFixtureFiles(__DIR__, $selectedFiles);
if ($files === []) {
    fwrite(STDERR, "No schema dump fixtures found.\n");
    exit(EXIT_ERROR);
}

$updatedFiles = [];
$hasPendingUpdates = false;

foreach ($files as $file) {
    $serialized = file_get_contents($file);
    if ($serialized === false) {
        fwrite(STDERR, "Failed to read: {$file}\n");
        exit(EXIT_ERROR);
    }

    $data = @unserialize($serialized);
    if ($data === false && $serialized !== serialize(false)) {
        fwrite(STDERR, "Failed to unserialize: {$file}\n");
        exit(EXIT_ERROR);
    }

    $visited = new SplObjectStorage();
    fixUninitializedTypedProperties($data, $knownDefaults, $visited, $file);

    $normalized = serialize($data);
    if ($normalized === $serialized) {
        continue;
    }

    $hasPendingUpdates = true;
    if ($write) {
        if (file_put_contents($file, $normalized) === false) {
            fwrite(STDERR, "Failed to write: {$file}\n");
            exit(EXIT_ERROR);
        }
        $updatedFiles[] = $file;
        echo "Updated {$file}\n";
    } else {
        echo "Needs update {$file}\n";
    }
}

if (!$hasPendingUpdates) {
    echo "All schema dump fixtures are up to date.\n";
    exit(EXIT_OK);
}

if (!$write) {
    echo "Run with --write to regenerate fixtures.\n";
    exit(EXIT_UPDATES_AVAILABLE);
}

echo sprintf("Updated %d fixture(s).\n", count($updatedFiles));
exit(EXIT_OK);

/**
 * @param array<int, string> $selectedFiles
 * @return array<int, string>
 */
function getFixtureFiles(string $baseDir, array $selectedFiles): array
{
    if ($selectedFiles !== []) {
        $files = [];
        foreach ($selectedFiles as $file) {
            if ($file === '') {
                continue;
            }
            $resolved = str_starts_with($file, '/')
                ? $file
                : realpath(getcwd() . DIRECTORY_SEPARATOR . $file);
            if ($resolved === false || !is_file($resolved)) {
                fwrite(STDERR, "Invalid --file path: {$file}\n");
                exit(EXIT_ERROR);
            }
            $files[] = $resolved;
        }

        return $files;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
    $files = [];
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $path = $item->getPathname();
        if (preg_match('/schema-dump-.*\.lock$/', $path) === 1) {
            $files[] = $path;
        }
    }
    sort($files);

    return $files;
}

/**
 * @param array<string, array<string, mixed>> $knownDefaults
 */
function fixUninitializedTypedProperties(
    mixed &$value,
    array $knownDefaults,
    SplObjectStorage $visited,
    string $file
): void {
    if (is_array($value)) {
        foreach ($value as &$nested) {
            fixUninitializedTypedProperties($nested, $knownDefaults, $visited, $file);
        }
        unset($nested);

        return;
    }

    if (!is_object($value) || $visited->offsetExists($value)) {
        return;
    }

    $visited[$value] = true;
    $class = new ReflectionClass($value);
    $defaultsByClass = $knownDefaults[$class->getName()] ?? [];

    foreach (getAllProperties($class) as $property) {
        if ($property->isStatic()) {
            continue;
        }

        if (!$property->isInitialized($value)) {
            if (array_key_exists($property->getName(), $defaultsByClass)) {
                $property->setValue($value, $defaultsByClass[$property->getName()]);
                continue;
            }
            $inferred = inferDefaultValue($property);
            if ($inferred['ok'] === false) {
                $className = $class->getName();
                $propertyName = $property->getName();
                fwrite(
                    STDERR,
                    "Cannot infer default for uninitialized typed property " .
                    "{$className}::\${$propertyName} in {$file}\n"
                );
                exit(EXIT_ERROR);
            }
            $property->setValue($value, $inferred['value']);
        }

        $nested = $property->getValue($value);
        fixUninitializedTypedProperties($nested, $knownDefaults, $visited, $file);
        $property->setValue($value, $nested);
    }
}

/**
 * @return array<int, ReflectionProperty>
 */
function getAllProperties(ReflectionClass $class): array
{
    $properties = [];
    do {
        foreach ($class->getProperties() as $property) {
            $declaringClass = $property->getDeclaringClass()->getName();
            $name = $declaringClass . '::' . $property->getName();
            $properties[$name] = $property;
        }
        $class = $class->getParentClass();
    } while ($class !== false);

    return array_values($properties);
}

/**
 * @return array{ok: bool, value?: mixed}
 */
function inferDefaultValue(ReflectionProperty $property): array
{
    $type = $property->getType();
    if ($type === null) {
        return ['ok' => true, 'value' => null];
    }

    if ($type->allowsNull()) {
        return ['ok' => true, 'value' => null];
    }

    if ($type instanceof ReflectionNamedType) {
        return match ($type->getName()) {
            'bool', 'false' => ['ok' => true, 'value' => false],
            'true' => ['ok' => true, 'value' => true],
            'int' => ['ok' => true, 'value' => 0],
            'float' => ['ok' => true, 'value' => 0.0],
            'string' => ['ok' => true, 'value' => ''],
            'array' => ['ok' => true, 'value' => []],
            default => ['ok' => false],
        };
    }

    return ['ok' => false];
}
