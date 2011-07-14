<?php
/**
 * DASBiT
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE
 *
 * @category   DASBiT
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */

/**
 * @namespace
 */
namespace Dasbit;

/**
 * Database access.
 *
 * @category   DASBiT
 * @package    Dasbit_Db
 * @copyright  Copyright (c) 2010 Ben Scholzen (http://www.dasprids.de)
 * @license    New BSD License
 */
class Database
{
    /**
     * Database adapter.
     * 
     * @var \PDO
     */
    protected $adapter;
    
    /**
     * Load a database.
     * 
     * @param  string $path
     * @param  array  $schema 
     * @return void
     */
    public function __construct($path, array $schema)
    {
        // Create or read the databse file
        $this->adapter = new \PDO('sqlite://' . $path);
        $this->adapter->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Get all existent tables
        $rows           = $this->fetchAll("SELECT name, sql FROM sqlite_master WHERE type = 'table'");
        $existentTables = array();
        
        foreach ($rows as $row) {
            if (preg_match('(CREATE TABLE ' . preg_quote($row['name']) . ' \((.*)\))s', $row['sql'], $matches) === 0) {
                throw new UnexpectedValueException('Incompatible table definition found');
            }

            $columns       = explode(',', $matches[1]);
            $parsedColumns = array();
            
            foreach ($columns as $column) {
                if (preg_match('(^(.*?)(?: (.*))?$)', trim($column), $matches) === 0) {
                    throw new UnexpectedValueException('Incompatible column definition found');
                }

                $parsedColumns[$matches[1]] = isset($matches[2]) ? $matches[2] : '';
            }
            
            $existentTables[$row['name']] = $parsedColumns;
        }
        
        // Check for tables to insert or update
        foreach ($schema as $tableName => $columns) {
            if (!isset($existentTables[$tableName])) {
                // Insert table
                $columnStrings = array();
                
                foreach ($columns as $columnName => $type) {
                    $columnStrings[] = $columnName . ' ' . $type;
                }
                
                $this->execute(sprintf('CREATE TABLE %s (%s)', $tableName, implode(', ', $columnStrings)));
            } else {
                // Check for new or changed columns
                foreach ($columns as $columnName => $type) {
                    if (!isset($existentTables[$tableName][$columnName])) {
                        $this->execute(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $tableName, $columnName, $type));
                    } elseif ($existentTables[$tableName][$columnName] !== $type) {
                        throw new RuntimeException('A column-definition was changed in schema, which is not supported by SQLITE');
                    }
                }
            }
        }

        // Check for tables to delete
        foreach ($existentTables as $tableName => $columns) {
            if (!isset($schema[$tableName])) {
                // Delete table
                $this->execute(sprintf('DROP TABLE %s', $tableName));
            } else {
                // Check for columns to delete
                foreach ($columns as $columnName => $type) {
                    if (!isset($schema[$tableName][$columnName])) {
                        throw new RuntimeException('A column was deleted in schema, which is not supported by SQLITE');
                    }
                }
            }
        }
    }
    
    /**
     * Insert a row into a table.
     * 
     * @param  string $table
     * @param  array  $columns
     * @return integer
     */
    public function insert($table, array $columns)
    {
        $keys   = array();
        $values = array();
        
        foreach ($columns as $key => $value) {
            $keys[]   = $key;
            $values[] = $this->quote($value);
        }
        
        return $this->execute(
            sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, implode(',', $keys), implode(',', $values))
        );
    }
    
    /**
     * Update rows in a table.
     * 
     * @param  string $table
     * @param  array  $columns
     * @param  string $where
     * @return integer
     */
    public function update($table, array $columns, $where)
    {
        $values = array();
        
        foreach ($columns as $key => $value) {
            $values[] = $key . ' => ' . $this->quote($value);
        }
        
        return $this->execute(
            sprintf("UPDATE %s SET %s WHERE %s", $table, implode(',', $values), $where)
        );
    }
    
    /**
     * Delete rows from a table.
     * 
     * @param  string $table
     * @param  string $where 
     * @return integer
     */
    public function delete($table, $where)
    {
        return $this->execute(
            sprintf("DELETE FROM %s WHERE", $table, $where)
        );
    }
    
    /**
     * Execute a statement.
     * 
     * @param  string $statement
     * @return integer
     */
    public function execute($statement)
    {
        return $this->adapter->exec($statement);
    }
    
    /**
     * Fetch a single row.
     * 
     * @param  string $statement 
     * @return array
     */
    public function fetchOne($statement)
    {
        return $this->adapter->query($statement)->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Fetch all rows.
     * 
     * @param  string $statement
     * @return array
     */
    public function fetchAll($statement)
    {
        return $this->adapter->query($statement)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Quote a value.
     * 
     * @param  mixed $value
     * @return string
     */
    public function quote($value)
    {
        return $this->adapter->quote($value);
    }
}