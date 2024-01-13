<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

/**
 * Wrapper Interface.
 */
interface WrapperInterface extends AdapterInterface
{
    /**
     * Class constructor, must always wrap another adapter.
     *
     * @param \Migrations\Db\Adapter\AdapterInterface $adapter Adapter
     */
    public function __construct(AdapterInterface $adapter);

    /**
     * Sets the database adapter to proxy commands to.
     *
     * @param \Migrations\Db\Adapter\AdapterInterface $adapter Adapter
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function setAdapter(AdapterInterface $adapter): AdapterInterface;

    /**
     * Gets the database adapter.
     *
     * @throws \RuntimeException if the adapter has not been set
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function getAdapter(): AdapterInterface;
}
