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
$tableMethod = $this->Migration->tableMethod($action);
$columnMethod = $this->Migration->columnMethod($action);
$indexMethod = $this->Migration->indexMethod($action);
%>
<?php
use Phinx\Migration\AbstractMigration;

class <%= $name %> extends AbstractMigration
{
    public function up()
    {
    <%- foreach ($tables as $table): %>
        $table = $this->table('<%= $table%>');
        $table
        <%- foreach ($this->Migration->columns($table) as $column => $config): %>
            -><%= $columnMethod %>('<%= $column %>', '<%= $config['columnType'] %>', [<%
                $options = [];
                $columnOptions = array_intersect_key($config['options'], $wantedOptions);
                echo $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>])
        <%- endforeach; %>
            -><%= $tableMethod %>();
    <%- endforeach; %>
    }
}
