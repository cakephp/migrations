<%
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

$wantedOptions = array_flip(['length', 'limit', 'default', 'unsigned', 'null']);
%>
<?php
use Phinx\Migration\AbstractMigration;

class <%= $name %> extends AbstractMigration {

    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * @return void
     */
    public function change()
    {
    <%- foreach ($tables as $table): %>
        $table = $this->table('<%= $table%>');
        $table
        <%- $tableSchema = $collection->describe($table); %>
        <%- foreach ($tableSchema->columns() as $column): %>
            ->addColumn('<%= $column %>', '<%= $tableSchema->columnType($column) %>', [<%
                $options = [];
                $columnOptions = $tableSchema->column($column);
                $columnOptions = array_intersect_key($columnOptions, $wantedOptions);
                foreach ($columnOptions as $optionName => $option) {
                    if ($optionName === 'length') {
                        $optionName = 'limit';
                    }
                    $options[$optionName] = $option;
                }

                echo $this->Bake->stringifyList($options, ['indent' => 4]);
            %>])
        <%- endforeach; %>
            ->save();
    <%- endforeach; %>
    }

    /**
     * Migrate Up.
     *
     * @return void
     */
    public function up()
    {
    }

    /**
     * Migrate Down.
     *
     * @return void
     */
    public function down()
    {
    }

}
