<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Cake\Database\Connection;
use Migrations\Db\AlterInstructions;

/**
 * MariaDB adapter.
 */
class MariadbAdapter extends MysqlAdapter
{
    /**
     * @inheritDoc
     */
    protected function getDropCheckConstraintInstructions(string $tableName, string $constraintName): AlterInstructions
    {
        $alter = sprintf('DROP CONSTRAINT %s', $this->quoteColumnName($constraintName));

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function hasNativeUuid(): bool
    {
        // Avoid connecting when an adapter without a configured connection is used as a stub.
        if (!$this->connection instanceof Connection || !$this->getOption('connection')) {
            return false;
        }
        $version = $this->getConnection()->getDriver()->version();

        return version_compare($version, '10.7', '>=');
    }
}
