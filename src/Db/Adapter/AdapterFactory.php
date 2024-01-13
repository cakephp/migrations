<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use RuntimeException;

/**
 * Adapter factory and registry.
 *
 * Used for registering adapters and creating instances of adapters.
 */
class AdapterFactory
{
    /**
     * @var static|null
     */
    protected static ?AdapterFactory $instance = null;

    /**
     * Get the factory singleton instance.
     *
     * @return static
     */
    public static function instance(): static
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Class map of database adapters, indexed by PDO::ATTR_DRIVER_NAME.
     *
     * @var array<string, \Phinx\Db\Adapter\AdapterInterface|string>
     * @phpstan-var array<string, \Phinx\Db\Adapter\AdapterInterface|class-string<\Phinx\Db\Adapter\AdapterInterface>>
     */
    protected array $adapters = [
        'mysql' => 'Migrations\Db\Adapter\MysqlAdapter',
        'postgres' => 'Migrations\Db\Adapter\PostgresAdapter',
        'sqlite' => 'Migrations\Db\Adapter\SqliteAdapter',
        'sqlserver' => 'Migrations\Db\Adapter\SqlserverAdapter',
    ];

    /**
     * Class map of adapters wrappers, indexed by name.
     *
     * @var array<string, \Phinx\Db\Adapter\WrapperInterface|string>
     */
    protected array $wrappers = [
        'record' => 'Migrations\Db\Adapter\RecordingAdapter',
        'timed' => 'Migrations\Db\Adapter\TimedOutputAdapter',
    ];

    /**
     * Register an adapter class with a given name.
     *
     * @param string $name Name
     * @param object|string $class Class
     * @throws \RuntimeException
     * @return $this
     */
    public function registerAdapter(string $name, object|string $class)
    {
        if (!is_subclass_of($class, 'Migrations\Db\Adapter\AdapterInterface')) {
            throw new RuntimeException(sprintf(
                'Adapter class "%s" must implement Migrations\\Db\\Adapter\\AdapterInterface',
                is_string($class) ? $class : get_class($class)
            ));
        }
        $this->adapters[$name] = $class;

        return $this;
    }

    /**
     * Get an adapter class by name.
     *
     * @param string $name Name
     * @throws \RuntimeException
     * @return object|string
     * @phpstan-return object|class-string<\Migrations\Db\Adapter\AdapterInterface>
     */
    protected function getClass(string $name): object|string
    {
        if (empty($this->adapters[$name])) {
            throw new RuntimeException(sprintf(
                'Adapter "%s" has not been registered',
                $name
            ));
        }

        return $this->adapters[$name];
    }

    /**
     * Get an adapter instance by name.
     *
     * @param string $name Name
     * @param array<string, mixed> $options Options
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function getAdapter(string $name, array $options): AdapterInterface
    {
        $class = $this->getClass($name);

        return new $class($options);
    }

    /**
     * Add or replace a wrapper with a fully qualified class name.
     *
     * @param string $name Name
     * @param object|string $class Class
     * @throws \RuntimeException
     * @return $this
     */
    public function registerWrapper(string $name, object|string $class)
    {
        if (!is_subclass_of($class, 'Migrations\Db\Adapter\WrapperInterface')) {
            throw new RuntimeException(sprintf(
                'Wrapper class "%s" must implement Migrations\\Db\\Adapter\\WrapperInterface',
                is_string($class) ? $class : get_class($class)
            ));
        }
        $this->wrappers[$name] = $class;

        return $this;
    }

    /**
     * Get a wrapper class by name.
     *
     * @param string $name Name
     * @throws \RuntimeException
     * @return \Migrations\Db\Adapter\WrapperInterface|string
     */
    protected function getWrapperClass(string $name): WrapperInterface|string
    {
        if (empty($this->wrappers[$name])) {
            throw new RuntimeException(sprintf(
                'Wrapper "%s" has not been registered',
                $name
            ));
        }

        return $this->wrappers[$name];
    }

    /**
     * Get a wrapper instance by name.
     *
     * @param string $name Name
     * @param \Migrations\Db\Adapter\AdapterInterface $adapter Adapter
     * @return \Migrations\Db\Adapter\AdapterWrapper
     */
    public function getWrapper(string $name, AdapterInterface $adapter): AdapterWrapper
    {
        $class = $this->getWrapperClass($name);

        return new $class($adapter);
    }
}
