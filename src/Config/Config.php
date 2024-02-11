<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Config;

use Closure;
use InvalidArgumentException;
use Phinx\Db\Adapter\SQLiteAdapter;
use Psr\Container\ContainerInterface;
use ReturnTypeWillChange;
use RuntimeException;
use UnexpectedValueException;

/**
 * Migrations configuration class.
 */
class Config implements ConfigInterface
{
    /**
     * The value that identifies a version order by creation time.
     */
    public const VERSION_ORDER_CREATION_TIME = 'creation';

    /**
     * The value that identifies a version order by execution time.
     */
    public const VERSION_ORDER_EXECUTION_TIME = 'execution';

    public const TEMPLATE_STYLE_CHANGE = 'change';
    public const TEMPLATE_STYLE_UP_DOWN = 'up_down';

    /**
     * @var array
     */
    protected array $values = [];

    /**
     * @var string|null
     */
    protected ?string $configFilePath = null;

    /**
     * @param array $configArray Config array
     * @param string|null $configFilePath Config file path
     */
    public function __construct(array $configArray, ?string $configFilePath = null)
    {
        $this->configFilePath = $configFilePath;
        $this->values = $configArray;
    }

    /**
     * @inheritDoc
     * @deprecated 4.2 To be removed in 5.x
     */
    public function getEnvironments(): ?array
    {
        if (isset($this->values['environments'])) {
            $environments = [];
            foreach ($this->values['environments'] as $key => $value) {
                if (is_array($value)) {
                    $environments[$key] = $value;
                }
            }

            return $environments;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getEnvironment(string $name): ?array
    {
        $environments = $this->getEnvironments();

        if (isset($environments[$name])) {
            if (
                isset($this->values['environments']['default_migration_table'])
                && !isset($environments[$name]['migration_table'])
            ) {
                $environments[$name]['migration_table'] =
                    $this->values['environments']['default_migration_table'];
            }

            if (
                isset($environments[$name]['adapter'])
                && $environments[$name]['adapter'] === 'sqlite'
                && !empty($environments[$name]['memory'])
            ) {
                $environments[$name]['name'] = SQLiteAdapter::MEMORY;
            }

            return $environments[$name];
        }

        return null;
    }

    /**
     * @inheritDoc
     * @deprecated 4.2 To be removed in 5.x
     */
    public function hasEnvironment(string $name): bool
    {
        return $this->getEnvironment($name) !== null;
    }

    /**
     * @inheritDoc
     * @deprecated 4.2 To be removed in 5.x
     */
    public function getDefaultEnvironment(): string
    {
        // The $PHINX_ENVIRONMENT variable overrides all other default settings
        $env = getenv('PHINX_ENVIRONMENT');
        if (!empty($env)) {
            if ($this->hasEnvironment($env)) {
                return $env;
            }

            throw new RuntimeException(sprintf(
                'The environment configuration (read from $PHINX_ENVIRONMENT) for \'%s\' is missing',
                $env
            ));
        }

        // if the user has configured a default environment then use it,
        // providing it actually exists!
        if (isset($this->values['environments']['default_environment'])) {
            if ($this->hasEnvironment($this->values['environments']['default_environment'])) {
                return $this->values['environments']['default_environment'];
            }

            throw new RuntimeException(sprintf(
                'The environment configuration for \'%s\' is missing',
                (string)$this->values['environments']['default_environment']
            ));
        }

        // else default to the first available one
        $environments = $this->getEnvironments();
        if (is_array($environments) && count($environments) > 0) {
            $names = array_keys($environments);

            return $names[0];
        }

        throw new RuntimeException('Could not find a default environment');
    }

    /**
     * @inheritDoc
     * @deprecated 4.2 To be removed in 5.x
     */
    public function getAlias($alias): ?string
    {
        return !empty($this->values['aliases'][$alias]) ? $this->values['aliases'][$alias] : null;
    }

    /**
     * @inheritDoc
     * @deprecated 4.2 To be removed in 5.x
     */
    public function getAliases(): array
    {
        return !empty($this->values['aliases']) ? $this->values['aliases'] : [];
    }

    /**
     * @inheritDoc
     */
    public function getConfigFilePath(): ?string
    {
        return $this->configFilePath;
    }

    /**
     * @inheritDoc
     * @throws \UnexpectedValueException
     */
    public function getMigrationPaths(): array
    {
        if (!isset($this->values['paths']['migrations'])) {
            throw new UnexpectedValueException('Migrations path missing from config file');
        }

        if (is_string($this->values['paths']['migrations'])) {
            $this->values['paths']['migrations'] = [$this->values['paths']['migrations']];
        }

        return $this->values['paths']['migrations'];
    }

    /**
     * @inheritDoc
     * @throws \UnexpectedValueException
     */
    public function getSeedPaths(): array
    {
        if (!isset($this->values['paths']['seeds'])) {
            throw new UnexpectedValueException('Seeds path missing from config file');
        }

        if (is_string($this->values['paths']['seeds'])) {
            $this->values['paths']['seeds'] = [$this->values['paths']['seeds']];
        }

        return $this->values['paths']['seeds'];
    }

    /**
     * @inheritdoc
     */
    public function getMigrationBaseClassName(bool $dropNamespace = true): string
    {
        $className = !isset($this->values['migration_base_class']) ? 'Phinx\Migration\AbstractMigration' : $this->values['migration_base_class'];

        return $dropNamespace ? (substr((string)strrchr($className, '\\'), 1) ?: $className) : $className;
    }

    /**
     * @inheritdoc
     */
    public function getSeedBaseClassName(bool $dropNamespace = true): string
    {
        $className = !isset($this->values['seed_base_class']) ? 'Phinx\Seed\AbstractSeed' : $this->values['seed_base_class'];

        return $dropNamespace ? substr((string)strrchr($className, '\\'), 1) : $className;
    }

    /**
     * @inheritdoc
     */
    public function getTemplateFile(): string|false
    {
        if (!isset($this->values['templates']['file'])) {
            return false;
        }

        return $this->values['templates']['file'];
    }

    /**
     * @inheritdoc
     */
    public function getTemplateClass(): string|false
    {
        if (!isset($this->values['templates']['class'])) {
            return false;
        }

        return $this->values['templates']['class'];
    }

    /**
     * @inheritdoc
     */
    public function getTemplateStyle(): string
    {
        if (!isset($this->values['templates']['style'])) {
            return self::TEMPLATE_STYLE_CHANGE;
        }

        return $this->values['templates']['style'] === self::TEMPLATE_STYLE_UP_DOWN ? self::TEMPLATE_STYLE_UP_DOWN : self::TEMPLATE_STYLE_CHANGE;
    }

    /**
     * @inheritDoc
     */
    public function getContainer(): ?ContainerInterface
    {
        if (!isset($this->values['container'])) {
            return null;
        }

        return $this->values['container'];
    }

    /**
     * @inheritdoc
     */
    public function getVersionOrder(): string
    {
        if (!isset($this->values['version_order'])) {
            return self::VERSION_ORDER_CREATION_TIME;
        }

        return $this->values['version_order'];
    }

    /**
     * @inheritdoc
     */
    public function isVersionOrderCreationTime(): bool
    {
        $versionOrder = $this->getVersionOrder();

        return $versionOrder == self::VERSION_ORDER_CREATION_TIME;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $id ID
     * @param mixed $value Value
     * @return void
     */
    public function offsetSet($id, $value): void
    {
        $this->values[$id] = $value;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $id ID
     * @throws \InvalidArgumentException
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        return $this->values[$id] instanceof Closure ? $this->values[$id]($this) : $this->values[$id];
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $id ID
     * @return bool
     */
    public function offsetExists($id): bool
    {
        return isset($this->values[$id]);
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $id ID
     * @return void
     */
    public function offsetUnset($id): void
    {
        unset($this->values[$id]);
    }

    /**
     * @inheritdoc
     */
    public function getSeedTemplateFile(): ?string
    {
        return $this->values['templates']['seedFile'] ?? null;
    }
}
