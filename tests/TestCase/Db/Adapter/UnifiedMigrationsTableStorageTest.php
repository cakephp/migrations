<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Adapter;

use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;
use PHPUnit\Framework\TestCase;

/**
 * Basic unit tests for UnifiedMigrationsTableStorage.
 *
 * Integration tests are covered by the adapter tests when running with
 * LEGACY_TABLES=false environment variable.
 */
class UnifiedMigrationsTableStorageTest extends TestCase
{
    public function testTableName(): void
    {
        $this->assertSame('cake_migrations', UnifiedMigrationsTableStorage::TABLE_NAME);
    }
}
