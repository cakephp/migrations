<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\TableMetadata;

class AddForeignKey extends Action
{
    /**
     * The foreign key to add
     *
     * @var \Migrations\Db\Table\ForeignKey
     */
    protected ForeignKey $foreignKey;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to add the foreign key to
     * @param \Migrations\Db\Table\ForeignKey $fk The foreign key to add
     */
    public function __construct(TableMetadata $table, ForeignKey $fk)
    {
        parent::__construct($table);
        $this->foreignKey = $fk;
    }

    /**
     * Creates a new AddForeignKey object after building the foreign key with
     * the passed attributes
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table object to add the foreign key to
     * @param string|string[] $columns The columns for the foreign key
     * @param \Migrations\Db\Table\TableMetadata|string $referencedTable The table the foreign key references
     * @param string|string[] $referencedColumns The columns in the referenced table
     * @param array<string, mixed> $options Extra options for the foreign key
     * @param string|null $name The name of the foreign key
     * @return self
     */
    public static function build(
        TableMetadata $table,
        string|array $columns,
        TableMetadata|string $referencedTable,
        string|array $referencedColumns = ['id'],
        array $options = [],
        ?string $name = null,
    ): self {
        if (is_string($referencedColumns)) {
            $referencedColumns = [$referencedColumns]; // str to array
        }

        if ($referencedTable instanceof TableMetadata) {
            $referencedTable = $referencedTable->getName();
        }

        // Shimming old 4.x
        if (isset($options['constraint'])) {
            $options['name'] = $options['constraint'];
            unset($options['constraint']);
        }

        $fk = new ForeignKey(
            name: $name ?? '',
            columns: (array)$columns,
            referencedTable: $referencedTable,
            referencedColumns: $referencedColumns,
        );
        $fk->setOptions($options);

        return new AddForeignKey($table, $fk);
    }

    /**
     * Returns the foreign key to be added
     *
     * @return \Migrations\Db\Table\ForeignKey
     */
    public function getForeignKey(): ForeignKey
    {
        return $this->foreignKey;
    }
}
