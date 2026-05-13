<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations;

/**
 * Marker interface for migrations that define separate `up()` and `down()` methods.
 *
 * When implemented, `Migrations\Migration\Environment` dispatches the migration via
 * `up()` or `down()` depending on the direction.
 *
 * In 5.x the method contracts are declared via PHPDoc only and are not enforced at
 * the type system level — implementations are still discovered through `method_exists`
 * for compatibility. The PHPDoc method tags exist so static analyzers and IDEs
 * resolve `up()` / `down()` once the interface is asserted via `instanceof`. The
 * 6.x release is expected to promote `up()` and `down()` to real abstract methods
 * on this interface.
 *
 * A migration implements either this interface or {@see ReversibleMigrationInterface},
 * never both.
 *
 * @method void up()   Apply the schema change.
 * @method void down() Revert the schema change.
 */
interface DirectionalMigrationInterface extends MigrationInterface
{
}
