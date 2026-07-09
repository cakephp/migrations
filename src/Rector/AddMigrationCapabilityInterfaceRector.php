<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Rector;

use Migrations\BaseMigration;
use Migrations\DirectionalMigrationInterface;
use Migrations\MigrationInterface;
use Migrations\ReversibleMigrationInterface;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ClassReflection;
use Rector\PHPStan\ScopeFetcher;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds `implements ReversibleMigrationInterface` or `implements DirectionalMigrationInterface`
 * to every class that extends {@see BaseMigration} based on the migration methods it defines.
 *
 * - Classes defining `change()` get {@see ReversibleMigrationInterface}.
 * - Classes defining both `up()` and `down()` get {@see DirectionalMigrationInterface}.
 * - One-way migrations (only `up()` or only `down()`) are left untouched and keep
 *   working through the Environment `method_exists()` fallback.
 * - Classes defining both styles are skipped (the user must pick a style deliberately).
 * - Classes already implementing either capability interface are skipped.
 *
 * This rule is meant to be run once during the upgrade from a pre-capability-interfaces
 * 5.x version of cakephp/migrations to a version that ships the interfaces.
 */
final class AddMigrationCapabilityInterfaceRector extends AbstractRector
{
    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add ReversibleMigrationInterface / DirectionalMigrationInterface to migration classes based on the method they define',
            [
                new CodeSample(
                    <<<'CODE_BEFORE'
use Migrations\BaseMigration;

class CreateProducts extends BaseMigration
{
    public function change(): void
    {
    }
}
CODE_BEFORE,
                    <<<'CODE_AFTER'
use Migrations\BaseMigration;
use Migrations\ReversibleMigrationInterface;

class CreateProducts extends BaseMigration implements ReversibleMigrationInterface
{
    public function change(): void
    {
    }
}
CODE_AFTER,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param \PhpParser\Node\Stmt\Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $classReflection = ScopeFetcher::fetch($node)->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return null;
        }

        // Both abstract bases and anonymous migrations are valid targets: abstract
        // bases propagate the interface to every leaf, and anonymous migrations are
        // baked the same way concrete ones are. The only excluded type is
        // BaseMigration itself, since it must remain style-agnostic.
        if (
            $classReflection->getName() === BaseMigration::class
            || !$classReflection->isSubclassOf(BaseMigration::class)
        ) {
            return null;
        }

        // Already annotated with either capability interface: leave it alone so the
        // rule stays idempotent and never ends up adding the second, conflicting one.
        if (
            $classReflection->implementsInterface(ReversibleMigrationInterface::class)
            || $classReflection->implementsInterface(DirectionalMigrationInterface::class)
        ) {
            return null;
        }

        $hasChange = $this->classHasMethod($node, MigrationInterface::CHANGE);
        $hasUp = $this->classHasMethod($node, MigrationInterface::UP);
        $hasDown = $this->classHasMethod($node, MigrationInterface::DOWN);

        // Mixed style is user error. Leave it untouched so the developer picks deliberately.
        if ($hasChange && ($hasUp || $hasDown)) {
            return null;
        }

        // Only adopt the directional interface when BOTH methods are present. A one-way
        // migration (only up() or only down()) must stay on the Environment method_exists()
        // fallback: DirectionalMigrationInterface makes Environment call the missing
        // direction unconditionally, turning a rollback no-op into a fatal error.
        $targetInterface = null;
        if ($hasChange) {
            $targetInterface = ReversibleMigrationInterface::class;
        } elseif ($hasUp && $hasDown) {
            $targetInterface = DirectionalMigrationInterface::class;
        }

        if ($targetInterface === null) {
            return null;
        }

        $node->implements[] = new FullyQualified($targetInterface);

        return $node;
    }

    /**
     * @param \PhpParser\Node\Stmt\Class_ $class Class node to inspect.
     * @param string $methodName Method name to look for on the class itself.
     * @return bool
     */
    private function classHasMethod(Class_ $class, string $methodName): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($this->getName($method) === $methodName) {
                return true;
            }
        }

        return false;
    }
}
