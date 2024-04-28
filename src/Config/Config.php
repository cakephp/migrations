<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Config;

use Closure;
use InvalidArgumentException;
use ReturnTypeWillChange;
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
     * @param array $configArray Config array
     */
    public function __construct(array $configArray)
    {
        $this->values = $configArray;
    }

    /**
     * @inheritDoc
     */
    public function getEnvironment(): ?array
    {
        if (empty($this->values['environment'])) {
            return null;
        }
        $config = (array)$this->values['environment'];
        $config['version_order'] = $this->getVersionOrder();

        return $config;
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
        /** @var string $className */
        $className = !isset($this->values['migration_base_class']) ? 'Phinx\Migration\AbstractMigration' : $this->values['migration_base_class'];

        return $dropNamespace ? (substr((string)strrchr($className, '\\'), 1) ?: $className) : $className;
    }

    /**
     * @inheritdoc
     */
    public function getSeedBaseClassName(bool $dropNamespace = true): string
    {
        /** @var string $className */
        $className = !isset($this->values['seed_base_class']) ? 'Phinx\Seed\AbstractSeed' : $this->values['seed_base_class'];

        return $dropNamespace ? substr((string)strrchr($className, '\\'), 1) : $className;
    }

    /**
     * @inheritdoc
     */
    public function getConnection(): string|false
    {
        return $this->values['environment']['connection'] ?? false;
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
