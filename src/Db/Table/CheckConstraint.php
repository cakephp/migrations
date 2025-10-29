<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use Cake\Database\Schema\CheckConstraint as DatabaseCheckConstraint;

/**
 * Check constraint value object
 *
 * Used to define check constraints that are added to tables as part of migrations.
 */
class CheckConstraint extends DatabaseCheckConstraint
{
}
