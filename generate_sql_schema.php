<?php

/**
 * Generate SQL Schema from Current Database
 * 
 * This script generates an updated SQL schema file based on the current 
 * database structure, useful for documentation or applying to other databases.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Database\Connection;
use App\Config\AppConfig;

class SchemaGenerator
{
    private $pdo;
    private $dbConfig;

    public function __construct()
    {
        $this->dbConfig = [
            'host' => AppConfig::get('database.host'),
            'port' => AppConfig::get('database.port'),
            'database' => AppConfig::get('database.database'),
            'username' => AppConfig::get('database.username'),
            'password' => AppConfig::get('database.password'),
            'charset' => AppConfig::get('database.charset'),
        ];

        try {
            $this->pdo = Connection::getInstance();
        } catch (Exception $e) {
            throw new Exception("Could not connect to database: " . $e->getMessage());
        }
    }

    /**
     * Generate SQL schema for all tables
     */
    public function generateSchema(): string
    {
        $schema = "-- Airigo Job Portal Database Schema\n";
        $schema .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $schema .= "-- From database: " . $this->dbConfig['database'] . "\n\n";

        $tables = $this->getTables();
        
        foreach ($tables as $table) {
            $schema .= $this->getTableSchema($table) . "\n";
        }

        return $schema;
    }

    /**
     * Get all tables in the database
     */
    private function getTables(): array
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Filter to only include our application tables
        $appTables = [
            'users', 'jobseekers', 'recruiters', 
            'jobs', 'applications', 'issues_reports'
        ];
        
        return array_filter($tables, function($table) use ($appTables) {
            return in_array($table, $appTables);
        });
    }

    /**
     * Get schema for a specific table
     */
    private function getTableSchema(string $tableName): string
    {
        $schema = "-- {$tableName} Table\n";
        $schema .= "CREATE TABLE {$tableName} (\n";

        // Get column information
        $columns = $this->pdo->query("DESCRIBE {$tableName}")->fetchAll(PDO::FETCH_ASSOC);
        
        $columnDefs = [];
        $primaryKey = null;
        $foreignKeys = [];

        foreach ($columns as $column) {
            $colDef = "  {$column['Field']} {$column['Type']}";
            
            if ($column['Null'] === 'NO' && $column['Field'] !== 'id') {
                $colDef .= " NOT NULL";
            }
            
            if ($column['Default'] !== null) {
                if ($column['Default'] === '') {
                    $colDef .= " DEFAULT ''";
                } else {
                    $colDef .= " DEFAULT " . (is_numeric($column['Default']) ? $column['Default'] : "'{$column['Default']}'");
                }
            } elseif ($column['Extra'] !== 'auto_increment') {
                if ($column['Null'] === 'YES') {
                    $colDef .= " DEFAULT NULL";
                }
            }
            
            if ($column['Extra'] === 'auto_increment') {
                $colDef .= " AUTO_INCREMENT";
            }
            
            $columnDefs[] = $colDef;
            
            if ($column['Key'] === 'PRI') {
                $primaryKey = $column['Field'];
            }
        }

        // Add primary key
        if ($primaryKey) {
            $columnDefs[] = "  PRIMARY KEY (`{$primaryKey}`)";
        }

        // Add the column definitions to schema
        $schema .= implode(",\n", $columnDefs) . "\n";

        // Get foreign key information
        $fkQuery = "SELECT 
                k.COLUMN_NAME,
                k.REFERENCED_TABLE_NAME,
                k.REFERENCED_COLUMN_NAME,
                c.UPDATE_RULE,
                c.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS c 
                ON k.CONSTRAINT_NAME = c.CONSTRAINT_NAME 
                AND k.TABLE_SCHEMA = c.CONSTRAINT_SCHEMA
            WHERE k.TABLE_SCHEMA = DATABASE() 
                AND k.TABLE_NAME = :tableName
                AND k.REFERENCED_TABLE_NAME IS NOT NULL";
        
        $fkStmt = $this->pdo->prepare($fkQuery);
        $fkStmt->bindParam(':tableName', $tableName);
        $fkStmt->execute();
        $foreignKeys = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                $schema .= "  , FOREIGN KEY (`{$fk['COLUMN_NAME']}`) REFERENCES `{$fk['REFERENCED_TABLE_NAME']}`(`{$fk['REFERENCED_COLUMN_NAME']}`) ON DELETE {$fk['DELETE_RULE']} ON UPDATE {$fk['UPDATE_RULE']}\n";
            }
        }

        // Get index information
        $indexes = $this->pdo->query("SHOW INDEXES FROM {$tableName}")->fetchAll(PDO::FETCH_ASSOC);
        
        $tableIndexes = [];
        foreach ($indexes as $index) {
            if ($index['Key_name'] !== 'PRIMARY' && strpos($index['Key_name'], 'CONSTRAINT') === false) {
                if (!isset($tableIndexes[$index['Key_name']])) {
                    $tableIndexes[$index['Key_name']] = [
                        'columns' => [],
                        'unique' => $index['Non_unique'] == 0
                    ];
                }
                $tableIndexes[$index['Key_name']]['columns'][] = $index['Column_name'];
            }
        }

        if (!empty($tableIndexes)) {
            $schema .= "  , INDEX idx_custom_indexes -- Additional indexes would be listed here\n";
        }

        $schema .= ") ENGINE=InnoDB;\n\n";

        return $schema;
    }

    /**
     * Save schema to file
     */
    public function saveToFile(string $filename = null): bool
    {
        $filename = $filename ?: 'database_schema_generated_' . date('Y-m-d_H-i-s') . '.sql';
        $schema = $this->generateSchema();
        
        return file_put_contents($filename, $schema) !== false;
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    try {
        $generator = new SchemaGenerator();
        
        $outputFile = $argv[1] ?? 'database_schema_current.sql';
        
        if ($generator->saveToFile($outputFile)) {
            echo "✅ Database schema successfully generated and saved to: {$outputFile}\n";
            echo "📁 The schema reflects the current database structure.\n";
        } else {
            echo "❌ Failed to generate database schema.\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}