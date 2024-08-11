<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Adapter;

use Migrations\Db\AlterInstructions;
use Migrations\Db\Literal;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table;

trait DefaultPdoAdapterTrait
{
    public function hasTransactions(): bool
    {
        return false;
    }

    public function beginTransaction(): void
    {
    }

    public function commitTransaction(): void
    {
    }

    public function rollbackTransaction(): void
    {
    }

    public function quoteTableName(string $tableName): string
    {
        return "'log'";
    }

    public function quoteColumnName(string $columnName): string
    {
        return $columnName;
    }

    public function hasTable(string $tableName): bool
    {
        return false;
    }

    public function createTable(Table $table, array $columns = [], array $indexes = []): void
    {
    }

    public function truncateTable(string $tableName): void
    {
    }

    public function getColumns(string $tableName): array
    {
        return [];
    }

    public function hasColumn(string $tableName, string $columnName): bool
    {
        return false;
    }

    public function hasIndex(string $tableName, array|string $columns): bool
    {
        return false;
    }

    public function hasIndexByName(string $tableName, string $indexName): bool
    {
        return false;
    }

    public function hasPrimaryKey(string $tableName, array|string $columns, ?string $constraint = null): bool
    {
        return false;
    }

    public function hasForeignKey(string $tableName, array|string $columns, ?string $constraint = null): bool
    {
        return false;
    }

    public function getSqlType(Literal|string $type, ?int $limit = null): array
    {
        return [];
    }

    public function createDatabase(string $name, array $options = []): void
    {
    }

    public function hasDatabase(string $name): bool
    {
        return false;
    }

    public function dropDatabase(string $name): void
    {
    }

    public function connect(): void
    {
    }

    public function disconnect(): void
    {
    }

    protected function getAddColumnInstructions(Table $table, Column $column): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getRenameColumnInstructions(string $tableName, string $columnName, string $newColumnName): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getChangeColumnInstructions(string $tableName, string $columnName, Column $newColumn): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getDropColumnInstructions(string $tableName, string $columnName): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getAddIndexInstructions(Table $table, Index $index): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getDropIndexByColumnsInstructions(string $tableName, array|string $columns): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getDropIndexByNameInstructions(string $tableName, string $indexName): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getDropForeignKeyInstructions(string $tableName, string $constraint): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getDropForeignKeyByColumnsInstructions(string $tableName, array $columns): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getDropTableInstructions(string $tableName): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getRenameTableInstructions(string $tableName, string $newTableName): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getChangePrimaryKeyInstructions(Table $table, array|string|null $newColumns): AlterInstructions
    {
        return new AlterInstructions();
    }

    protected function getChangeCommentInstructions(Table $table, ?string $newComment): AlterInstructions
    {
        return new AlterInstructions();
    }
}
