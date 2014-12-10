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
<% foreach ($tables as $table): %>
<%= "\n\t\t\$table = \$this->table('$table');"; %>
<% // Get a single table (instance of Schema\Table) %>
<% $tableSchema = $collection->describe($table); %>
<% // columns of the table %>
<% $columns = $tableSchema->columns(); %>
    <%= "\$table"; %>
<% foreach ($columns as $column): %>
      <%= "->addColumn('" . $column . "', '" . $tableSchema->columnType($column) . "', ["; %>
<% foreach ($tableSchema->column($column) as $optionName => $option): %>
<% if (in_array($optionName, ['length', 'limit', 'default', 'unsigned', 'null'])): %>
        <%= "'" . str_replace('length', 'limit', $optionName) . "' => '" .  $option . "', "; %>
<% endif; %>
<% endforeach; %>
      <%= "])"; %>
<% endforeach; %>
      <%= "->save();"; %>
<% endforeach; %>
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
