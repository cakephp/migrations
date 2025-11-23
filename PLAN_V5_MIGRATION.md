# Plan of Attack: Migrations v5.0 - Consolidated Migration Tracking

## Overview
This document outlines the implementation plan for migrating from plugin-specific `{plugin}_phinxlog` tables to a unified `cake_migrations` table in v5.0. This is part of the larger effort to remove phinx dependency and modernize the migrations plugin.

**Reference**: https://github.com/cakephp/migrations/issues/822#issuecomment-3169983834

## Goals
1. Consolidate all migration tracking into a single `cake_migrations` table
2. Use clean new structure by default (no legacy checks on every operation)
3. Provide opt-in legacy shim via feature flag for upgrade path
4. Simplify codebase by removing plugin-specific table naming logic
5. Make upgrade/rollback explicit and controllable

## Current State Analysis

### Existing Architecture
- **Current table naming**: `phinxlog` (app) and `{plugin}_phinxlog` (plugins)
- **Logic location**: `src/Util/UtilTrait.php::getPhinxTable()`
- **References**: 26 files reference `phinxlog` directly or indirectly
- **Key files**:
  - `src/Migration/Manager.php` - Migration execution and status
  - `src/Migration/Environment.php` - Environment/adapter interaction
  - `src/Db/Adapter/AbstractAdapter.php` - Database operations
  - `src/Util/UtilTrait.php` - Table name generation

## Implementation Plan

### Phase 1: New Schema Design

#### 1.1 Design `cake_migrations` Table Schema
**New columns needed:**
- `id` (auto-increment primary key)
- `version` (bigint/varchar - migration timestamp)
- `migration_name` (varchar - human readable name)
- `start_time` (timestamp - when migration started)
- `end_time` (timestamp - when migration completed)
- `breakpoint` (boolean - existing phinx feature)
- `plugin` (varchar, nullable - NULL for app, plugin name for plugins)
- `executed` (boolean/tinyint - migration status)

**Key design decisions:**
- Use `plugin` column to distinguish app vs plugin migrations
- NULL `plugin` value = application migrations
- Non-NULL `plugin` value = plugin migrations (e.g., 'Blog', 'TestBlog')
- Unique constraint on (`version`, `plugin`)

#### 1.2 Create Migration Table Builder
**Location**: `src/Migration/MigrationTable.php`

**Responsibilities:**
- Create `cake_migrations` table if not exists
- Handle table schema definition
- Provide upgrade path from legacy tables

### Phase 2: Legacy Shim Feature Flag

#### 2.1 Configuration Flag
**Location**: `config/app.php` or `config/migrations.php`

**Configuration:**
```php
'Migrations' => [
    'legacyTables' => null, // Default: autodetect
]
```

**Behavior:**
- `legacyTables = null` (default): **Autodetect** - check if old tables exist, use them if found
- `legacyTables = false`: Always use new `cake_migrations` table, upgrade commands hidden
- `legacyTables = true`: Force legacy mode, show upgrade commands, use old table structure

**Autodetect Logic:**
```php
// Pseudo-code for autodetect
if (legacyTables === null) {
    if (phinxlog_table_exists() || any_plugin_phinxlog_exists()) {
        // Use legacy mode automatically
        $useLegacyTables = true;
        $showUpgradeCommands = true;
    } else {
        // Use new table structure
        $useLegacyTables = false;
        $showUpgradeCommands = false;
    }
}
```

**Benefits:**
- **Seamless upgrade path** - existing apps automatically continue working
- New apps automatically use new table structure (no old tables detected)
- No documentation reading required for basic upgrade
- Explicit opt-out for new apps: `legacyTables = false`
- Explicit opt-in for testing/rollback: `legacyTables = true`
- Upgrade commands appear automatically when old tables detected

#### 2.2 Legacy Shim Implementation
**Location**: `src/Migration/LegacyShim.php` (new class)

**Responsibilities:**
- Only loaded when `legacyTables = true`
- Provide backward-compatible table name resolution
- Override `getPhinxTable()` to use old naming scheme
- Used during upgrade/migration period only

#### 2.3 Data Migration Process (Explicit Command)
**Command**: `bin/cake migrations upgrade`

**Steps for each legacy table:**
1. Detect table name pattern (extract plugin name)
2. Read all rows from legacy table
3. **Move** data into `cake_migrations` with appropriate `plugin` value:
   - `phinxlog` → `plugin = NULL`
   - `blog_phinxlog` → `plugin = 'Blog'`
   - `test_blog_phinxlog` → `plugin = 'TestBlog'`
4. Empty legacy tables after successful copy
5. Optionally drop legacy tables (or leave empty for manual cleanup)
6. Log migration results

**Flags:**
- `--dry-run`: Show what would be migrated without making changes

**Considerations:**
- Move data (copy then delete from source)
- Handle duplicate versions across plugins (should be prevented by unique constraint)
- Preserve breakpoint status
- Preserve execution timestamps
- Transaction safety (all-or-nothing migration)
- Empty old tables after successful migration

### Phase 3: Code Refactoring

#### 3.1 Update UtilTrait
**File**: `src/Util/UtilTrait.php`

**Changes**:
- Modify `getPhinxTable()` to check feature flag with autodetect
- Return `'cake_migrations'` for new apps
- Use legacy shim when `legacyTables = true` or autodetected
- Deprecate `$plugin` parameter (no longer used in new mode)
- Update docblock

**Before:**
```php
protected function getPhinxTable(?string $plugin = null): string
{
    $table = 'phinxlog';
    if (!$plugin) {
        return $table;
    }
    $plugin = Inflector::underscore($plugin) . '_';
    $plugin = str_replace(['\\', '/', '.'], '_', $plugin);
    return $plugin . $table;
}
```

**After:**
```php
protected function getPhinxTable(?string $plugin = null): string
{
    $config = Configure::read('Migrations.legacyTables', null);

    // Autodetect mode: check if old tables exist
    if ($config === null) {
        $useLegacy = $this->detectLegacyTables();
    } else {
        $useLegacy = $config === true;
    }

    // Legacy mode: use old plugin-specific table names
    if ($useLegacy) {
        $table = 'phinxlog';
        if (!$plugin) {
            return $table;
        }
        $plugin = Inflector::underscore($plugin) . '_';
        $plugin = str_replace(['\\', '/', '.'], '_', $plugin);
        return $plugin . $table;
    }

    // v5.0+: All migrations tracked in unified table
    return 'cake_migrations';
}

protected function detectLegacyTables(): bool
{
    // Cache the detection result to avoid repeated DB queries
    static $detected = null;
    if ($detected !== null) {
        return $detected;
    }

    $connection = $this->getConnection();
    $schema = $connection->getSchemaCollection();
    $tables = $schema->listTables();

    // Check for phinxlog or any {plugin}_phinxlog table
    foreach ($tables as $table) {
        if ($table === 'phinxlog' || str_ends_with($table, '_phinxlog')) {
            $detected = true;
            return true;
        }
    }

    $detected = false;
    return false;
}
```

#### 3.2 Update Database Adapter Layer
**Files**: `src/Db/Adapter/*.php`

**Changes**:
1. Update `getVersionLog()` queries to:
   - Query `cake_migrations` table
   - Filter by `plugin` column value
   - Maintain same return format

2. Update `migrated()` method to:
   - Insert with `plugin` column value
   - Use NULL for app migrations

3. Update `toggleBreakpoint()` to:
   - Include `plugin` in WHERE clause
   - Use (`version`, `plugin`) for uniqueness

**Key query updates:**
```sql
-- Old: SELECT version, breakpoint FROM phinxlog
-- New: SELECT version, breakpoint FROM cake_migrations WHERE plugin IS NULL

-- Old: SELECT version, breakpoint FROM blog_phinxlog
-- New: SELECT version, breakpoint FROM cake_migrations WHERE plugin = 'Blog'
```

#### 3.3 Update Manager Class
**File**: `src/Migration/Manager.php`

**Changes**:
- Pass plugin context to Environment/Adapter
- Ensure plugin name is available throughout migration lifecycle
- Update status printing to show plugin association

#### 3.4 Update Commands
**Files**: `src/Command/*.php`

**Changes**:
- Update output messages (remove `phinxlog` references in default mode)
- Update help text to reflect new table name
- Hide/show upgrade commands based on `legacyTables` flag

**Command Visibility Logic:**
```php
// In UpgradeCommand and UpgradeRollbackCommand classes
public function isVisible(): bool
{
    $config = Configure::read('Migrations.legacyTables', null);

    // Show if explicitly enabled
    if ($config === true) {
        return true;
    }

    // Show if autodetect mode and old tables detected
    if ($config === null) {
        return $this->detectLegacyTables();
    }

    // Hidden if explicitly disabled
    return false;
}
```

**Benefits:**
- Clean command list for new projects (no old tables = commands hidden)
- Upgrade commands appear automatically for existing projects (old tables detected)
- Explicit control available via config if needed
- No confusion for new projects starting with v5.0

### Phase 4: Testing Strategy

#### 4.1 Unit Tests
**Create tests for:**
- `cake_migrations` table creation
- Legacy data migration logic (upgrade command)
- Rollback logic (upgrade-rollback command)
- Plugin name extraction from table names
- Query filtering by plugin
- Duplicate handling
- Transaction rollback on errors
- Feature flag behavior (null/autodetect, true, false)
- Autodetect logic (presence of phinxlog tables)
- Data move integrity (source emptied, target populated)
- Static caching of detection result

**Files to create:**
- `tests/TestCase/Migration/MigrationTableTest.php`
- `tests/TestCase/Command/UpgradeCommandTest.php`
- `tests/TestCase/Command/UpgradeRollbackCommandTest.php`

#### 4.2 Integration Tests
**Update existing tests:**
- All adapter tests to use `cake_migrations` (default mode)
- Manager tests for multi-plugin scenarios
- Command tests for new table references
- Middleware tests
- Add legacy mode tests (with `legacyTables = true`)

**Test scenarios:**
- Fresh install (no legacy tables, autodetect = use new table)
- Fresh install with `legacyTables = false` explicit
- Existing app (has phinxlog, autodetect = use old tables)
- Upgrade with existing `phinxlog`
- Upgrade with multiple plugin tables
- Mixed app + plugin migrations
- Full upgrade → rollback → upgrade cycle
- Command visibility based on autodetect
- Command visibility with explicit true/false config
- Data integrity after move (verify all records present)
- Empty legacy tables after upgrade
- Populated legacy tables after rollback
- Autodetect caching behavior

#### 4.3 Test Fixtures
**Create test data:**
- Sample `phinxlog` data
- Sample plugin phinxlog tables
- Edge cases (empty tables, only breakpoints, etc.)

### Phase 5: Documentation

#### 5.1 Update User Documentation
**Files**: `docs/en/*.rst`

**Changes**:
- Update table name references
- Document upgrade process
- Add troubleshooting section
- Update schema diagrams

#### 5.2 Migration Guide
**Create**: `docs/en/migrations/5.0-migration-guide.rst`

**Content**:
- Overview of changes
- Automatic vs manual upgrade
- Verification steps
- Rollback instructions (if needed)
- FAQ section

#### 5.3 Code Comments
- Update inline comments referencing phinxlog
- Add migration history notes
- Document plugin column usage

### Phase 6: Upgrade Commands

#### 6.1 Create Upgrade Commands
**Files**:
- `src/Command/UpgradeCommand.php`
- `src/Command/UpgradeRollbackCommand.php`

**Visibility**: When `Migrations.legacyTables = true` OR when autodetect finds old tables

**Command 1: upgrade**
- **Moves** data from legacy tables to `cake_migrations`
- Empties old tables after successful move (or drops them)
- `--dry-run` flag shows preview without making changes
- Transaction-safe (rollback on error)

**Command 2: upgrade-rollback**
- **Moves** data back from `cake_migrations` to legacy tables
- Empties `cake_migrations` after successful move
- Used if issues are discovered after upgrade
- Transaction-safe

**Usage**:
```bash
# Commands automatically visible if old tables detected (autodetect mode)
bin/cake migrations upgrade --dry-run      # preview
bin/cake migrations upgrade                # move data to new table

# Set feature flag to false to use new table
'Migrations' => ['legacyTables' => false]

# Test application with new table structure

# If issues occur, remove/comment flag (back to autodetect):
# Old tables still exist (emptied), so no data loss
bin/cake migrations upgrade-rollback       # move data back to old tables
```

**Workflow (Autodetect - Default):**
1. User upgrades to v5.0
2. Old `phinxlog` tables detected automatically
3. Plugin continues using old tables (no breaking change!)
4. Upgrade commands appear in `bin/cake migrations` list
5. User runs `bin/cake migrations upgrade --dry-run` to preview
6. User runs `bin/cake migrations upgrade` to **move** data
7. User sets `'Migrations' => ['legacyTables' => false]` in config
8. Application now uses new `cake_migrations` table
9. Commands disappear from help (no longer visible)
10. If issues: remove config (back to null), run `upgrade-rollback`

**Workflow (New App):**
1. User starts new project with v5.0
2. No old tables detected
3. Plugin uses new `cake_migrations` table automatically
4. Upgrade commands never appear
5. Zero configuration needed!

**Post-Upgrade Cleanup:**
- Empty legacy tables can be dropped manually via SQL
- Or add option to `upgrade` command: `--drop-tables` to drop immediately

**Rollback:**
Remove/comment out the `legacyTables` config (back to autodetect) and run `bin/cake migrations upgrade-rollback`

## Implementation Order

### Recommended Sequence

1. **Create new infrastructure** (Phase 1)
   - Design and implement `cake_migrations` schema
   - Create `MigrationTable` builder class

2. **Implement feature flag** (Phase 2)
   - Add `legacyTables` configuration support
   - Create `upgrade` command (only visible when flag enabled)
   - Create `upgrade-rollback` command (moves data back)
   - Implement data move logic with `--dry-run` option

3. **Refactor core code** (Phase 3)
   - Update `UtilTrait` with feature flag check
   - Update adapters to use new table structure by default
   - Update Manager for plugin column support
   - Update commands to hide/show based on feature flag

4. **Write tests** (Phase 4)
   - Unit tests for new components
   - Test both modes (legacy flag on/off)
   - Update existing integration tests
   - Test upgrade scenarios and rollback

5. **Update documentation** (Phase 5)
   - User-facing docs
   - Migration guide with feature flag workflow
   - Code comments

6. **Finalize upgrade path** (Phase 6)
   - Test complete upgrade workflow
   - Verify rollback process
   - Polish command output and error messages

## Key Considerations

### Backward Compatibility
- Breaking change in major version (expected)
- Explicit upgrade process (user controls when to migrate)
- Existing migrations continue to work with `legacyTables = true`
- Proper rollback command if upgrade causes issues
- Old tables emptied after successful move (can be dropped manually)

### Performance
- **Minimal runtime overhead**: Autodetect runs once per request (cached via static)
- One-time DB query to check for legacy tables (on first call only)
- Explicit `legacyTables = false` removes autodetect overhead entirely
- One-time migration cost on explicit upgrade
- Query performance should be similar or better (indexed plugin column)
- Reduced table count improves database management
- Clean API in new apps (no detection needed if no old tables)

### Error Handling
- Transaction wrapping for atomic migration
- Detailed error messages
- Rollback capability
- Logging for debugging

### Edge Cases
- Plugin name conflicts in table names
- Custom migration table names (if supported)
- Locked tables during migration
- Partial migration failures
- Very large migration histories

## Validation Criteria

### Success Metrics
- All existing tests pass
- New upgrade tests pass
- Zero data loss in migration
- Plugin migrations remain isolated
- Performance benchmarks maintained

### User Acceptance
- **Zero-config upgrade**: Existing apps automatically continue working
- **Zero-config new apps**: New projects use new table automatically
- Explicit upgrade process with clear workflow when ready
- Feature flag provides safety net during transition
- Rollback available via upgrade-rollback command
- Upgrade commands only visible when needed (autodetect or explicit)
- Minimal documentation reading required

## Timeline Estimate

**Development**: ~2-3 weeks
- Phase 1-2: 3-5 days (schema + migration logic)
- Phase 3: 4-6 days (refactoring)
- Phase 4: 3-5 days (testing)
- Phase 5: 1-2 days (documentation)
- Phase 6: 1-2 days (optional upgrade command)

**Testing & Review**: 1 week
**Buffer for issues**: 1 week

**Total**: ~4-5 weeks

## Risks & Mitigations

### Risk: Data loss during migration
**Mitigation**:
- Wrap in transaction (atomic move operation)
- `--dry-run` flag to preview changes
- Rollback command available if issues occur
- Transaction rollback on any error during upgrade
- Extensive testing with various scenarios

### Risk: Plugin name extraction errors
**Mitigation**:
- Well-tested regex/parsing logic
- Clear error messages
- Data remains in old tables if upgrade fails

### Risk: Performance degradation
**Mitigation**:
- Index on (`version`, `plugin`)
- Query optimization
- Benchmark testing
- **No runtime overhead in default mode** (no legacy checks)

### Risk: User confusion during upgrade
**Mitigation**:
- Clear upgrade guide with step-by-step workflow
- Feature flag makes process explicit and controlled
- Dedicated rollback command for reverting upgrade
- Upgrade commands only visible when `legacyTables = true`
- Helpful error messages with guidance
- FAQ documentation covering common scenarios

## Notes
- This plan assumes NO rebuild step, only upgrade
- **Default behavior (null)**: Autodetect legacy tables, seamless upgrade
- New apps automatically use new `cake_migrations` table structure
- Existing apps automatically continue using old tables until explicitly upgraded
- Legacy shim activated via autodetect OR explicit `legacyTables = true`
- Old tables data **moved** (not copied) - cleaner state management
- Dedicated rollback command to reverse upgrade if needed
- Minimal runtime overhead (one cached DB query for autodetect)
- Commands auto-show/hide based on autodetect or explicit config
- Focus is on zero-config, automatic behavior with explicit control available
- Maintains compatibility with existing migration files
- Data lives in one place at a time (old tables OR new table, never both)
- **No breaking changes on upgrade** - old tables detected and used automatically!
