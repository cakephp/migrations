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
$constraints = $foreignKeys = $dropForeignKeys = [];
%>
<?php
use Phinx\Migration\AbstractMigration;

class <%= $name %> extends AbstractMigration
{
    public function up()
    {
    <%- foreach ($tables as $table):
        $foreignKeys = [];
        $primaryKeysColumns = $this->Migration->primaryKeysColumnsList($table);
        $primaryKeys = $this->Migration->primaryKeys($table);
        $specialPk = count($primaryKeys) > 1 || $primaryKeys[0]['name'] !== 'id' || $primaryKeys[0]['info']['columnType'] !== 'integer';
        if ($specialPk):
        %>
        $table = $this->table('<%= $table%>', ['id' => false, 'primary_key' => ['<%= implode("', '", \Cake\Utility\Hash::extract($primaryKeys, '{n}.name')) %>']]);
        <%- else: %>
        $table = $this->table('<%= $table%>');
        <%- endif; %>
        $table
        <%- if ($specialPk):
            foreach ($primaryKeys as $primaryKey) : %>
            -><%= $columnMethod %>('<%= $primaryKey['name'] %>', '<%= $primaryKey['info']['columnType'] %>', [<%
                $options = [];
                $columnOptions = array_intersect_key($primaryKey['info']['options'], $wantedOptions);
                echo $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>])
            <%- endforeach;
            endif;
        foreach ($this->Migration->columns($table) as $column => $config): %>
            -><%= $columnMethod %>('<%= $column %>', '<%= $config['columnType'] %>', [<%
                $options = [];
                $columnOptions = array_intersect_key($config['options'], $wantedOptions);
                echo $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>])
        <%- endforeach;
            $tableConstraints = $this->Migration->constraints($table);
            if (!empty($tableConstraints)):
                sort($tableConstraints);
                $constraints[$table] = $tableConstraints;

                foreach ($constraints[$table] as $name => $constraint):
                    if ($constraint['type'] !== 'unique'):
                        $foreignKeys[] = $constraint['columns'];
                    endif;
                    if ($constraint['columns'] !== $primaryKeysColumns): %>
            ->addIndex(
                [<% echo $this->Migration->stringifyList($constraint['columns'], ['indent' => 5]); %>]<% echo ($constraint['type'] === 'unique') ? ',' : ''; %>

                <%- if ($constraint['type'] === 'unique'): %>
                ['unique' => true]
                <%- endif; %>
            )
                    <%- endif;
                endforeach;
            endif;

            foreach($this->Migration->indexes($table) as $index):
                sort($foreignKeys);
                $indexColumns = $index['columns'];
                sort($indexColumns);
                if (in_array($foreignKeys, $indexColumns)):
                %>
                ->addIndex(
                    [<%
                        echo $this->Migration->stringifyList($index['columns'], ['indent' => 5]);
                    %>]
                )
            <%- endif;
            endforeach; %>
            -><%= $tableMethod %>();

    <%- endforeach; %>
    <%- foreach ($constraints as $table => $tableConstraints):
            foreach ($tableConstraints as $constraint):
                $constraintColumns = $constraint['columns'];
                sort($constraintColumns);
                if ($constraint['type'] !== 'unique'):
                    $columnsList = '\'' . $constraint['columns'][0] . '\'';
                    if (count($constraint['columns']) > 1):
                        $columnsList = '[' . $this->Migration->stringifyList($constraint['columns'], ['indent' => 5]) . ']';
                    endif;
                    $dropForeignKeys[$table][] = $columnsList;

                    if (is_array($constraint['references'][1])):
                        $columnsReference = '[' . $this->Migration->stringifyList($constraint['references'][1], ['indent' => 5]) . ']';
                    else:
                        $columnsReference = '\'' . $constraint['references'][1] . '\'';
                    endif;
                    $statement = $this->Migration->tableStatement($table);
                    if (!empty($statement)): %>
        <%= $statement %>
                    <%- endif; %>
            ->addForeignKey(
                <%= $columnsList %>,
                '<%= $constraint['references'][0] %>',
                <%= $columnsReference %>,
                [
                    'update' => '<%= $constraint['update'] %>',
                    'delete' => '<%= $constraint['delete'] %>'
                ]
            )
                <%- endif; %>
        <%- endforeach; %>
        <%- if (isset($this->Migration->tableStatements[$table])): %>
            ->update();

        <%- endif; %>
    <%- endforeach; %>
    }

    public function down()
    {
        <%- foreach ($dropForeignKeys as $table => $columnsList): %>
        $this->table('<%= $table %>')
            <%- foreach ($columnsList as $columns): %>
            ->dropForeignKey(
                <%= $columns %>
            )
            <%- endforeach; %>
            ->update();

        <%- endforeach; %>
        <%- foreach ($tables as $table): %>
        $this->dropTable('<%= $table%>');
        <%- endforeach; %>
    }
}
