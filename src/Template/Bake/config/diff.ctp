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

$tables = $data['fullTables'];
unset($data['fullTables']);
$constraints = [];
%>
<?php
use Migrations\AbstractMigration;

class <%= $name %> extends AbstractMigration
{

    public function up()
    {
        <%- foreach ($data as $tableName => $tableDiff):
            $hasRemoveFK = !empty($tableDiff['constraints']['remove']) || !empty($tableDiff['indexes']['remove']);
        %>
        <%- if ($hasRemoveFK): %>
        $this->table('<%= $tableName %>')
        <%- endif; %>
            <%- if (!empty($tableDiff['constraints']['remove'])): %>
            <%- foreach ($tableDiff['constraints']['remove'] as $constraintName => $constraintDefinition): %>
            ->dropForeignKey([], '<%= $constraintName %>')
            <%- endforeach; %>
            <%- endif; %>
            <%- if (!empty($tableDiff['indexes']['remove'])): %>
            <%- foreach ($tableDiff['indexes']['remove'] as $indexName => $indexDefinition): %>
            ->removeIndexByName('<%= $indexName %>')
            <%- endforeach; %>
            <%- endif; %>
        <%- if ($hasRemoveFK): %>
            ->update();

        <%- endif; %>
        <%- if (!empty($tableDiff['columns']['remove'])):
            $statement = $this->Migration->tableStatement($tableName);
            if (!empty($statement)): %>
        <%= $statement %>
            <%- endif; %>
        <%- foreach ($tableDiff['columns']['remove'] as $columnName => $columnDefinition): %>
            ->removeColumn('<%= $columnName %>')
        <%- endforeach; %>
        <%- endif; %>
        <%- if (!empty($tableDiff['columns']['changed'])):
            $statement = $this->Migration->tableStatement($tableName);
            if (!empty($statement)): %>
        <%= $statement %>
            <%- endif; %>
        <%- foreach ($tableDiff['columns']['changed'] as $columnName => $columnAttributes):
            $type = $columnAttributes['type'];
            unset($columnAttributes['type']);
            $columnAttributes = $this->Migration->getColumnOption($columnAttributes);
            $columnAttributes = $this->Migration->stringifyList($columnAttributes, ['indent' => 4]);
            if (!empty($columnAttributes)): %>
            ->changeColumn('<%= $columnName %>', '<%= $type %>', [<%= $columnAttributes %>])
            <%- else: %>
            ->changeColumn('<%= $columnName %>', '<%= $type %>')
            <%- endif; %>
        <%- endforeach;
            $statement = $this->Migration->tableStatement($tableName);
            if (empty($statement)): %>
            ->update();
            <%- endif; %>
        <%- endif; %>
        <%- endforeach; %>
        <%- if (!empty($tables['add'])):
            foreach ($tables['add'] as $table => $schema):
                $foreignKeys = [];
                $primaryKeysColumns = $this->Migration->primaryKeysColumnsList($table);
                $primaryKeys = $this->Migration->primaryKeys($table);
                $specialPk = (count($primaryKeys) > 1 || $primaryKeys[0]['name'] !== 'id' || $primaryKeys[0]['info']['columnType'] !== 'integer') && $autoId;
                if ($specialPk): %>

        $table = $this->table('<%= $table%>', ['id' => false, 'primary_key' => ['<%= implode("', '", \Cake\Utility\Hash::extract($primaryKeys, '{n}.name')) %>']]);
                <%- else: %>

        $table = $this->table('<%= $table%>');
                    <%- endif; %>
        $table
                <%- if ($specialPk):
                foreach ($primaryKeys as $primaryKey) : %>
            ->addColumn('<%= $primaryKey['name'] %>', '<%= $primaryKey['info']['columnType'] %>', [<%
                $columnOptions = $this->Migration->getColumnOption($primaryKey['info']['options']);
                echo $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>])
                <%- endforeach; %>
            ->addPrimaryKey(['<%= implode("', '", \Cake\Utility\Hash::extract($primaryKeys, '{n}.name')) %>'])
            <%- endif;
            foreach ($this->Migration->columns($table) as $column => $config): %>
            ->addColumn('<%= $column %>', '<%= $config['columnType'] %>', [<%
                $columnOptions = $this->Migration->getColumnOption($config['options']);
                echo $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>])
            <%- endforeach;
                $tableConstraints = $this->Migration->constraints($table);
                if (!empty($tableConstraints)):
                    sort($tableConstraints);
                    $constraints[$table] = $tableConstraints;

                    foreach ($constraints[$table] as $name => $constraint):
                        if ($constraint['type'] === 'foreign'):
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
                    if (!in_array($indexColumns, $foreignKeys)):
                    %>
            ->addIndex(
                [<% echo $this->Migration->stringifyList($index['columns'], ['indent' => 5]); %>]<% echo ($index['type'] === 'unique') ? ',' : ''; %>

                <%- if ($index['type'] === 'unique'): %>
                    ['unique' => true]
                <%- endif; %>
            )
            <%- endif;
                endforeach; %>
            ->create();
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
                    $statement = $this->Migration->tableStatement($table, true);
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
        <%- endif; %>
        <%- foreach ($data as $tableName => $tableDiff): %>
            <%- if (!empty($tableDiff['columns']['add'])): %>

        $this->table('<%= $tableName %>')
            <%- foreach ($tableDiff['columns']['add'] as $columnName => $columnAttributes):
                $type = $columnAttributes['type'];
                unset($columnAttributes['type']);

                $columnAttributes = $this->Migration->getColumnOption($columnAttributes);
                $columnAttributes = $this->Migration->stringifyList($columnAttributes, ['indent' => 4]);
                if (!empty($columnAttributes)): %>
            ->addColumn('<%= $columnName %>', '<%= $type %>', [<%= $columnAttributes %>])
                <%- else: %>
            ->addColumn('<%= $columnName %>', '<%= $type %>')
                <%- endif; %>
                <%- endforeach; %>
            ->update();
            <%- endif; %>
            <%- if (!empty($tableDiff['indexes']['add'])): %>

        $this->table('<%= $tableName %>')
            <%- foreach ($tableDiff['indexes']['add'] as $indexName => $index): %>
            ->addIndex(
                [<% echo $this->Migration->stringifyList($index['columns'], ['indent' => 5]); %>],
                [<%
                $params = ['name' => $indexName];
                if ($index['type'] === 'unique'):
                    $params['unique'] = true;
                endif;
                echo $this->Migration->stringifyList($params, ['indent' => 5]);
                %>]
            )
            <%- endforeach; %>
            ->update();
            <%- endif; %>
            <%- if (!empty($tableDiff['constraints']['add'])): %>
            <%- foreach ($tableDiff['constraints']['add'] as $constraintName => $constraint):
                $constraintColumns = $constraint['columns'];
                sort($constraintColumns);
                if ($constraint['type'] !== 'unique'):
                    $columnsList = '\'' . $constraint['columns'][0] . '\'';
                    if (count($constraint['columns']) > 1):
                        $columnsList = '[' . $this->Migration->stringifyList($constraint['columns'], ['indent' => 5]) . ']';
                    endif;
                    $dropForeignKeys[$tableName][] = $columnsList;

                    if (is_array($constraint['references'][1])):
                        $columnsReference = '[' . $this->Migration->stringifyList($constraint['references'][1], ['indent' => 5]) . ']';
                    else:
                        $columnsReference = '\'' . $constraint['references'][1] . '\'';
                    endif;
                    $statement = $this->Migration->tableStatement($tableName, true);
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
            <%- endif; %>
        <%- endforeach; %>

        <%- if (!empty($tables['remove'])): %>
        <%- foreach ($tables['remove'] as $tableName => $table): %>
            $this->dropTable('<%= $tableName %>');
            <%- endforeach; %>
        <%- endif; %>
    }


    public function down()
    {
        <%- $constraints = [];
            foreach ($dropForeignKeys as $table => $columnsList):
            $maxKey = count($columnsList) - 1;
        %>
        $this->table('<%= $table %>')
            <%- foreach ($columnsList as $key => $columns): %>
            ->dropForeignKey(
                <%= $columns %>
            )<%= ($key === $maxKey) ? ';' : '' %>
            <%- endforeach; %>
        <%- endforeach; %>
        <%- if (!empty($tables['remove'])):
        foreach ($tables['remove'] as $table => $schema):
            $foreignKeys = [];
            $primaryKeysColumns = $this->Migration->primaryKeysColumnsList($schema);
            $primaryKeys = $this->Migration->primaryKeys($schema);
            $specialPk = (count($primaryKeys) > 1 || $primaryKeys[0]['name'] !== 'id' || $primaryKeys[0]['info']['columnType'] !== 'integer') && $autoId;
            if ($specialPk): %>

            $table = $this->table('<%= $table%>', ['id' => false, 'primary_key' => ['<%= implode("', '", \Cake\Utility\Hash::extract($primaryKeys, '{n}.name')) %>']]);
            <%- else: %>

            $table = $this->table('<%= $table%>');
            <%- endif; %>
            $table
            <%- if ($specialPk):
            foreach ($primaryKeys as $primaryKey) : %>
                ->addColumn('<%= $primaryKey['name'] %>', '<%= $primaryKey['info']['columnType'] %>', [<%
                $columnOptions = $this->Migration->getColumnOption($primaryKey['info']['options']);
                echo $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>])
                <%- endforeach; %>
            ->addPrimaryKey(['<%= implode("', '", \Cake\Utility\Hash::extract($primaryKeys, '{n}.name')) %>'])
            <%- endif;
            foreach ($this->Migration->columns($schema) as $column => $config): %>
                ->addColumn('<%= $column %>', '<%= $config['columnType'] %>', [<%
                $columnOptions = $this->Migration->getColumnOption($config['options']);
                echo $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>])
            <%- endforeach;
                $tableConstraints = $this->Migration->constraints($schema);
                if (!empty($tableConstraints)):
                    sort($tableConstraints);
                    $constraints[$table] = $tableConstraints;

                    foreach ($constraints[$table] as $name => $constraint):
                        if ($constraint['type'] === 'foreign'):
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

                foreach($this->Migration->indexes($schema) as $index):
                    sort($foreignKeys);
                    $indexColumns = $index['columns'];
                    sort($indexColumns);
                    if (!in_array($indexColumns, $foreignKeys)):
                        %>
                        ->addIndex(
                        [<% echo $this->Migration->stringifyList($index['columns'], ['indent' => 5]); %>]<% echo ($index['type'] === 'unique') ? ',' : ''; %>

                <%- if ($index['type'] === 'unique'): %>
                        ['unique' => true]
                        <%- endif; %>
            )
            <%- endif;
                endforeach; %>
            ->create();
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
                $statement = $this->Migration->tableStatement($table, true);
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
        <%- endif; %>

        <%- if (!empty($tableDiff['constraints']['add'])): %>
            <%- foreach ($tableDiff['constraints']['add'] as $constraintName => $constraint):
            $constraintColumns = $constraint['columns'];
            sort($constraintColumns);
            if ($constraint['type'] !== 'unique'):
                $columnsList = '\'' . $constraint['columns'][0] . '\'';
                if (count($constraint['columns']) > 1):
                    $columnsList = '[' . $this->Migration->stringifyList($constraint['columns'], ['indent' => 5]) . ']';
                endif;
                $dropForeignKeys[$tableName][] = $columnsList;

                if (is_array($constraint['references'][1])):
                    $columnsReference = '[' . $this->Migration->stringifyList($constraint['references'][1], ['indent' => 5]) . ']';
                else:
                    $columnsReference = '\'' . $constraint['references'][1] . '\'';
                endif;
                $statement = $this->Migration->tableStatement($tableName, true);
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
        <%- endif; %>
        <%- foreach ($data as $tableName => $tableDiff): %>
            <%- if (!empty($tableDiff['indexes']['add'])): %>

        $this->table('<%= $tableName %>')
                <%- foreach ($tableDiff['indexes']['add'] as $indexName => $index): %>
            ->removeIndexByName('<%= $indexName %>')
                <%- endforeach %>
            ->update();
            <%- endif; %>
            <%- if (!empty($tableDiff['indexes']['remove'])): %>

        $this->table('<%= $tableName %>')
            <%- foreach ($tableDiff['indexes']['remove'] as $indexName => $indexDefinition): %>
            ->addIndex(
                [<% echo $this->Migration->stringifyList($indexDefinition['columns'], ['indent' => 5]); %>],
                [<%
                $params = ['name' => $indexName];
                if ($indexDefinition['type'] === 'unique'):
                    $params['unique'] = true;
                endif;
                echo $this->Migration->stringifyList($params, ['indent' => 5]);
                %>]
            )
            <%- endforeach; %>
            ->update();
        <%- endif; %>
        <%- if (!empty($tableDiff['columns']['remove'])): %>

        $this->table('<%= $tableName %>')
        <%- foreach ($tableDiff['columns']['remove'] as $columnName => $columnDefinition):
                $type = $columnDefinition['type'];
                unset($columnDefinition['type']);
                $columnOptions = $this->Migration->getColumnOption($columnDefinition);
                $columnOptions = $this->Migration->stringifyList($columnOptions, ['indent' => 4]);
            %>
            ->addColumn('<%= $columnName %>', '<%= $type %>', [<%= $columnOptions %>])
        <%- endforeach; %>
            ->update();
        <%- endif; %>
        <%- if (!empty($tableDiff['columns']['changed'])):
            $oldTableDef = $dumpSchema[$tableName];
            %>

        $this->table('<%= $tableName %>')
            <%- foreach ($tableDiff['columns']['changed'] as $columnName => $columnAttributes):
            $columnAttributes = $oldTableDef->column($columnName);
            $type = $columnAttributes['type'];
            unset($columnAttributes['type']);
            $columnAttributes = $this->Migration->getColumnOption($columnAttributes);
            $columnAttributes = $this->Migration->stringifyList($columnAttributes, ['indent' => 4]);
            if (!empty($columnAttributes)): %>
            ->changeColumn('<%= $columnName %>', '<%= $type %>', [<%= $columnAttributes %>])
            <%- else: %>
            ->changeColumn('<%= $columnName %>', '<%= $type %>')
                <%- endif; %>
            <%- endforeach; %>
            ->update();
        <%- endif; %>
        <%- if (!empty($tableDiff['columns']['add'])): %>

        $this->table('<%= $tableName %>')
            <%- foreach ($tableDiff['columns']['add'] as $columnName => $columnAttributes): %>
            ->removeColumn('<%= $columnName %>')
            <%- endforeach; %>
            ->update();
            <%- endif; %>
        <%- endforeach; %>

        <%- if (!empty($tables['add'])): %>
            <%- foreach ($tables['add'] as $tableName => $table): %>
        $this->dropTable('<%= $tableName %>');
            <%- endforeach; %>
        <%- endif; %>
    }
}
