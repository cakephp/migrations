# Column Attribute Preservation - Implementation Summary

## Overview

This feature adds the ability to modify columns while preserving existing attributes, addressing GitHub issue [cakephp/phinx#123](https://github.com/cakephp/phinx/issues/123).

## Solution: New `updateColumn()` Method

We introduced a new `updateColumn()` method that preserves column attributes by default, while keeping `changeColumn()` unchanged for backwards compatibility.

## Changes Made

### 1. Source Code (`src/Db/Table.php`)

#### New Method: `updateColumn()`
```php
public function updateColumn(
    string $columnName,
    string|Column|Literal|null $newColumnType,
    array $options = []
)
```

- **Purpose**: Modify columns while preserving unspecified attributes
- **Default Behavior**: Automatically preserves attributes
- **Null Type Support**: Pass `null` to preserve the existing column type

#### Modified Method: `changeColumn()`
- **Added null type support**: Can now accept `null` as the type parameter
- **Added preservation option**: `'preserve_unspecified' => true/false`
- **Default**: `preserve_unspecified => false` (backwards compatible)
- **BC Safe**: Existing code works exactly as before

#### New Helper Method: `mergeColumnOptions()`
```php
protected function mergeColumnOptions(
    Column $existingColumn,
    string|Literal $newColumnType,
    array $options
): array
```

- Intelligently merges existing column attributes with new options
- Preserves: default, null, limit, scale, comment, signed, collation, encoding, values
- Smart type handling: doesn't preserve limit when type changes

### 2. Tests (`tests/TestCase/Db/Adapter/MysqlAdapterTest.php`)

Added 9 comprehensive test cases:

1. `testChangeColumnPreservesDefaultValue()` - Verify attribute preservation
2. `testChangeColumnPreservesDefaultValueWithDifferentType()` - Type change handling
3. `testChangeColumnCanExplicitlyOverrideDefault()` - Explicit overrides work
4. `testChangeColumnCanDisablePreserveUnspecified()` - Opt-out mechanism
5. `testChangeColumnWithNullTypePreservesType()` - Null type parameter
6. `testChangeColumnWithNullTypeOnNonExistentColumnThrows()` - Error handling
7. `testUpdateColumnPreservesAttributes()` - New updateColumn() method
8. `testChangeColumnDoesNotPreserveByDefault()` - BC verification
9. `testChangeColumnWithPreserveUnspecifiedTrue()` - Opt-in mechanism

### 3. Documentation (`docs/en/writing-migrations.rst`)

- Added section "Updating Columns (Recommended)"
- Documented `updateColumn()` method with examples
- Explained attribute preservation
- Listed all preserved attributes
- Renamed old section to "Changing Columns (Traditional)"
- Added note recommending `updateColumn()` for most use cases
- Documented `preserve_unspecified` option for `changeColumn()`

## Usage Examples

### Basic Usage (Recommended)
```php
// Make column nullable, preserve everything else
$table->updateColumn('email', null, ['null' => true]);
```

### Change Type, Preserve Attributes
```php
// Change to biginteger, preserve default and null
$table->updateColumn('user_id', 'biginteger');
```

### Change Default, Preserve Type
```php
// Change default, preserve type and other attributes
$table->updateColumn('status', null, ['default' => 'active']);
```

### Traditional Method (BC)
```php
// Old way still works exactly as before
$table->changeColumn('email', 'string', [
    'null' => true,
    'default' => null,
    'limit' => 255,
]);
```

### Opt-in Preservation with changeColumn()
```php
// Use changeColumn with preservation
$table->changeColumn('email', null, [
    'null' => true,
    'preserve_unspecified' => true,
]);
```

## Preserved Attributes

When using `updateColumn()` or `changeColumn()` with `preserve_unspecified => true`:

- ✅ Default value
- ✅ NULL/NOT NULL constraint
- ✅ Column limit/length (only if type unchanged)
- ✅ Decimal scale/precision
- ✅ Column comment
- ✅ Signed/unsigned (numeric types)
- ✅ Collation
- ✅ Character encoding
- ✅ Enum/set values

## Backwards Compatibility

✅ **100% Backwards Compatible**

- `changeColumn()` behavior unchanged (does NOT preserve by default)
- Existing migrations work without modification
- No breaking changes to existing code
- New functionality is opt-in via new method

## Code Quality

- ✅ PHPStan Level 7: No errors
- ✅ PHPCS: No code style violations
- ✅ Fully type-hinted
- ✅ Comprehensive test coverage (9 test cases)
- ✅ Complete documentation

## Benefits

1. **Safer Migrations**: Prevents accidental loss of defaults and other attributes
2. **Less Code**: Only specify what you're changing
3. **More Intuitive**: Matches expectations from other ORMs
4. **BC Safe**: Existing code continues to work
5. **Flexible**: Multiple approaches available
6. **Well Documented**: Clear guidance in official docs

## Migration Path

### For New Code
Use `updateColumn()` - it's safer and requires less code:
```php
$table->updateColumn('column', null, ['null' => true]);
```

### For Existing Code
No changes needed - everything continues to work as before.

### To Modernize Existing Migrations (Optional)
Replace verbose `changeColumn()` calls with simpler `updateColumn()`:
```php
// Before
$table->changeColumn('email', 'string', [
    'null' => true,
    'default' => 'test',
    'limit' => 255,
]);

// After
$table->updateColumn('email', null, ['null' => true]);
```

## Related Issues

- **Solves**: https://github.com/cakephp/phinx/issues/123
- **Inspired by**: Rails ActiveRecord `change_column` behavior

## Files Modified

1. `src/Db/Table.php` - Core implementation
2. `tests/TestCase/Db/Adapter/MysqlAdapterTest.php` - Test coverage
3. `docs/en/writing-migrations.rst` - User documentation

## Credits

Implementation addresses longstanding community request for safer column modifications with attribute preservation.
