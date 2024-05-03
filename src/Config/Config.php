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
    public function getMigrationPath(): string
    {
        if (!isset($this->values['paths']['migrations'])) {
            throw new UnexpectedValueException('Migrations path missing from config file');
        }
        if (is_array($this->values['paths']['migrations']) && isset($this->values['paths']['migrations'][0])) {
            return $this->values['paths']['migrations'][0];
        }

        return $this->values['paths']['migrations'];
    }

    /**
     * @inheritDoc
     * @throws \UnexpectedValueException
     */
    public function getSeedPath(): string
    {
        if (!isset($this->values['paths']['seeds'])) {
            throw new UnexpectedValueException('Seeds path missing from config file');
        }
        if (is_array($this->values['paths']['seeds']) && isset($this->values['paths']['seeds'][0])) {
            return $this->values['paths']['seeds'][0];
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
     * @param mixed $offset ID
     * @param mixed $value Value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->values[$offset] = $value;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $offset ID
     * @throws \InvalidArgumentException
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (!array_key_exists($offset, $this->values)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $offset));
        }

        return $this->values[$offset] instanceof Closure ? $this->values[$offset]($this) : $this->values[$offset];
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $offset ID
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->values[$offset]);
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $offset ID
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->values[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function getSeedTemplateFile(): ?string
    {
        return $this->values['templates']['seedFile'] ?? null;
    }
}
