<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db;

/**
 * Insert mode enumeration
 *
 * Defines different insertion strategies for handling duplicate key conflicts.
 */
enum InsertMode: string
{
    /**
     * Standard INSERT - fails on duplicate key conflicts
     */
    case INSERT = 'insert';

    /**
     * INSERT IGNORE - skips rows that would cause duplicate key conflicts
     *
     * - MySQL: INSERT IGNORE
     * - PostgreSQL: ON CONFLICT DO NOTHING
     * - SQLite: INSERT OR IGNORE
     */
    case IGNORE = 'ignore';

    /**
     * UPSERT - inserts or updates rows on duplicate key conflicts
     *
     * - MySQL: ON DUPLICATE KEY UPDATE
     * - PostgreSQL: ON CONFLICT (...) DO UPDATE SET
     * - SQLite: ON CONFLICT (...) DO UPDATE SET
     */
    case UPSERT = 'upsert';
}
