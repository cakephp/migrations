<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Closure;
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
     * @var array<string, string|\Closure>
     * @phpstan-var array<string, class-string<\Migrations\Db\Adapter\AdapterInterface>|\Closure>
     * @psalm-var array<string, class-string<\Migrations\Db\Adapter\AdapterInterface>|\Closure>
     */
    protected array $adapters = [
        'mysql' => MysqlAdapter::class,
        'postgres' => PostgresAdapter::class,
        'sqlite' => SqliteAdapter::class,
        'sqlserver' => SqlserverAdapter::class,
    ];

    /**
     * Class map of adapters wrappers, indexed by name.
     *
     * @var array<string, string>
     * @psalm-var array<string, class-string<\Migrations\Db\Adapter\WrapperInterface>>
     */
    protected array $wrappers = [
        'record' => RecordingAdapter::class,
        'timed' => TimedOutputAdapter::class,
    ];

    /**
     * Register an adapter class with a given name.
     *
     * @param string $name Name
     * @param \Closure|string $class Class or factory method for the adapter.
     * @throws \RuntimeException
     * @return $this
     */
    public function registerAdapter(string $name, Closure|string $class)
    {
        if (
            !($class instanceof Closure || is_subclass_of($class, AdapterInterface::class))
        ) {
            throw new RuntimeException(sprintf(
                'Adapter class "%s" must implement Migrations\\Db\\Adapter\\AdapterInterface',
                $class
            ));
        }
        $this->adapters[$name] = $class;

        return $this;
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
        if (empty($this->adapters[$name])) {
            throw new RuntimeException(sprintf(
                'Adapter "%s" has not been registered',
                $name
            ));
        }
        $classOrFactory = $this->adapters[$name];
        if ($classOrFactory instanceof Closure) {
            return $classOrFactory($options);
        }

        return new $classOrFactory($options);
    }

    /**
     * Add or replace a wrapper with a fully qualified class name.
     *
     * @param string $name Name
     * @param string $class Class
     * @throws \RuntimeException
     * @return $this
     */
    public function registerWrapper(string $name, string $class)
    {
        if (!is_subclass_of($class, WrapperInterface::class)) {
            throw new RuntimeException(sprintf(
                'Wrapper class "%s" must implement Migrations\\Db\\Adapter\\WrapperInterface',
                $class
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
     * @return class-string<\Migrations\Db\Adapter\WrapperInterface>
     */
    protected function getWrapperClass(string $name): string
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
     * @return \Migrations\Db\Adapter\WrapperInterface
     */
    public function getWrapper(string $name, AdapterInterface $adapter): WrapperInterface
    {
        $class = $this->getWrapperClass($name);

        return new $class($adapter);
    }
}
