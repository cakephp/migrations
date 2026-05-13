<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations;

/**
 * Marker interface for migrations that define a single reversible `change()` method.
 *
 * When implemented, `Migrations\Migration\Environment` dispatches the migration via
 * `change()` and uses the recording adapter to invert commands for the down direction.
 *
 * In 5.x the method contract is declared via PHPDoc only and is not enforced at the
 * type system level — implementations are still discovered through `method_exists`
 * for compatibility. The PHPDoc method tag exists so static analyzers and IDEs
 * resolve `change()` once the interface is asserted via `instanceof`. The 6.x
 * release is expected to promote `change()` to a real abstract method on this
 * interface.
 *
 * A migration implements either this interface or {@see DirectionalMigrationInterface},
 * never both.
 *
 * @method void change() Reversible schema change. Use the recording adapter for down().
 */
interface ReversibleMigrationInterface extends MigrationInterface
{
}
