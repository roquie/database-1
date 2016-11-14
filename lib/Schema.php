<?php
/* ===========================================================================
 * Copyright 2013-2016 The Opis Project
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace Opis\Database;

use Opis\Database\Schema\CreateTable;
use Opis\Database\Schema\AlterTable;

class Schema
{
    /** @var    \Opis\Database\Connection   Connection. */
    protected $connection;

    /** @var    array   Table list. */
    protected $tableList;

    /** @var    string  Currently used database name. */
    protected $currentDatabase;

    /** @var    array   Column list */
    protected $columns = array();

    /**
     * Constructor
     *
     * @param   \Opis\Database\Connection   $connection Connection.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the name of the currently used database
     *
     * @return  string
     */
    public function getCurrentDatabase()
    {
        if ($this->currentDatabase === null) {
            $compiler = $this->connection->schemaCompiler();
            $result = $compiler->currentDatabase($this->connection->getDSN());

            if (is_array($result)) {
                $this->currentDatabase = $this->connection->column($result['sql'], $result['params']);
            } else {
                $this->currentDatabase = $result;
            }
        }

        return $this->currentDatabase;
    }

    /**
     * Check if the specified table exists
     *
     * @param   string  $table  Table name
     * @param   boolean $clear  (optional) Refresh table list
     *
     * @return  boolean
     */
    public function hasTable(string $table, bool $clear = false): bool
    {
        $list = $this->getTables($clear);
        return isset($list[strtolower($table)]);
    }

    /**
     * Get a list with all tables that belong to the currently used database
     *
     * @param   boolean $clear  (optional) Refresh table list
     *
     * @return  string[]
     */
    public function getTables(bool $clear = false): array
    {
        if ($clear) {
            $this->tableList = null;
        }

        if ($this->tableList === null) {
            $compiler = $this->connection->schemaCompiler();

            $database = $this->getCurrentDatabase();

            $sql = $compiler->getTables($database);

            $results = $this->connection
                ->query($sql['sql'], $sql['params'])
                ->fetchNum()
                ->all();

            $this->tableList = array();

            foreach ($results as $result) {
                $this->tableList[strtolower($result[0])] = $result[0];
            }
        }

        return $this->tableList;
    }

    /**
     * Get a list with all columns that belong to the specified table
     *
     * @param   string  $table
     * @param   boolean $clear (optional) Refresh column list
     * @param   boolean $names (optional) Return only the column names
     * 
     * @return false|string[]
     */
    public function getColumns(string $table, bool $clear = false, bool $names = true)
    {
        if ($clear) {
            unset($this->columns[$table]);
        }

        if (!$this->hasTable($table, $clear)) {
            return false;
        }

        if (!isset($this->columns[$table])) {
            $compiler = $this->connection->schemaCompiler();

            $database = $this->getCurrentDatabase();

            $sql = $compiler->getColumns($database, $table);

            $results = $this->connection
                ->query($sql['sql'], $sql['params'])
                ->fetchAssoc()
                ->all();

            $columns = array();

            foreach ($results as $ord => &$col) {
                $columns[$col['name']] = array(
                    'name' => $col['name'],
                    'type' => $col['type'],
                );
            }

            $this->columns[$table] = $columns;
        }

        return $names ? array_keys($this->columns[$table]) : $this->columns[$table];
    }

    /**
     * Creates a new table
     *
     * @param   string      $table      Table name
     * @param   callable    $callback   A callback that will define table's fields and indexes
     */
    public function create(string $table, callable $callback)
    {
        $compiler = $this->connection->schemaCompiler();

        $schema = new CreateTable($table);

        $callback($schema);

        foreach ($compiler->create($schema) as $result) {
            $this->connection->command($result['sql'], $result['params']);
        }

        //clear table list
        $this->tableList = null;
    }

    /**
     * Alters a table's definition
     *
     * @param   string      $table      Table name
     * @param   callable    $callback   A callback that will add or remove fields or indexes
     */
    public function alter(string $table, callable $callback)
    {
        $compiler = $this->connection->schemaCompiler();

        $schema = new AlterTable($table);

        $callback($schema);

        unset($this->columns[strtolower($table)]);

        foreach ($compiler->alter($schema) as $result) {
            $this->connection->command($result['sql'], $result['params']);
        }
    }

    /**
     * Change a table's name
     *
     * @param   string  $table  The table
     * @param   string  $name   The new name of the table
     */
    public function renameTable(string $table, string $name)
    {
        $result = $this->connection->schemaCompiler()->renameTable($table, $name);
        $this->connection->command($result['sql'], $result['params']);
        $this->tableList = null;
        unset($this->columns[strtolower($table)]);
    }

    /**
     * Deletes a table
     *
     * @param   string  $table  Table name
     */
    public function drop(string $table)
    {
        $compiler = $this->connection->schemaCompiler();

        $result = $compiler->drop($table);

        $this->connection->command($result['sql'], $result['params']);

        //clear table list
        $this->tableList = null;
        unset($this->columns[strtolower($table)]);
    }

    /**
     * Deletes all records from a table
     *
     * @param   string  $table  Table name
     */
    public function truncate(string $table)
    {
        $compiler = $this->connection->schemaCompiler();

        $result = $compiler->truncate($table);

        $this->connection->command($result['sql'], $result['params']);
    }
}
