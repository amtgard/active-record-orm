#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/repository_common.php';

/**
 * Generate CREATE TABLE SQL
 * Made public for use by audit command
 */
function generateCreateTableSql(array $schema, bool $isAuditLog = false): string
{
    $tableName = $schema['tableName'];
    $fields = $schema['fields'];
    $primaryKey = $schema['primaryKey'];
    
    if ($isAuditLog) {
        $tableName = $tableName . '_audit_log';
    }
    
    $sql = "CREATE TABLE `$tableName` (\n";
    
    $columnDefinitions = [];
    
    if ($isAuditLog) {
        // Add audit log primary key first
        $columnDefinitions[] = "    `id` INT NOT NULL AUTO_INCREMENT";
        // Add audit log specific fields
        $columnDefinitions[] = "    `record_id` INT NOT NULL";
        $columnDefinitions[] = "    `log_datetime` DATETIME NOT NULL";
        $columnDefinitions[] = "    `fields` MEDIUMTEXT NULL";
        $columnDefinitions[] = "    `action` ENUM('insert', 'update', 'delete') NOT NULL";
    }
    
    // Add regular fields (excluding primary key for audit log)
    foreach ($fields as $field) {
        if ($isAuditLog && $field['isPrimaryKey']) {
            // Skip primary key field in audit log - use record_id instead
            continue;
        }
        
        $dbName = $field['dbName'];
        $mysqlType = phpTypeToMySqlType($field['phpType'], $field['nullable']);
        $nullable = $field['nullable'] ? 'NULL' : 'NOT NULL';
        
        $columnDef = "    `$dbName` $mysqlType $nullable";
        
        // Add AUTO_INCREMENT for primary key if it's an integer (only for non-audit tables)
        if (!$isAuditLog && $field['isPrimaryKey'] && str_contains($field['phpType'], 'int')) {
            $columnDef .= ' AUTO_INCREMENT';
        }
        
        $columnDefinitions[] = $columnDef;
    }
    
    $sql .= implode(",\n", $columnDefinitions);
    
    // Primary key
    if ($isAuditLog) {
        // Audit log table uses 'id' as primary key
        $sql .= ",\n    PRIMARY KEY (`id`)";
    } elseif ($primaryKey) {
        // Regular table uses its own primary key
        $sql .= ",\n    PRIMARY KEY (`$primaryKey`)";
    }
    
    $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    return $sql;
}

/**
 * Print help for schema command
 */
function printSchemaHelp(): void
{
    $help = "SCHEMA COMMAND:\n";
    $help .= "    Generate MySQL CREATE TABLE SQL from existing RepositoryEntity class definitions.\n\n";
    $help .= "    repository.php schema --source=<path> [--table=<table>]\n\n";
    $help .= "    Options:\n";
    $help .= "        --source=<path>      Directory containing Repository and RepositoryEntity classes\n";
    $help .= "        --table=<table>      Optional: Table name in snake_case (e.g., 'user_profiles')\n";
    $help .= "                            If omitted, generates SQL for all RepositoryEntity classes found\n";
    $help .= "        --help, -h           Show this help message\n\n";
    $help .= "    Examples:\n";
    $help .= "        # Generate SQL for a specific table\n";
    $help .= "        repository.php schema --source=./src/Entity --table=user_profiles\n\n";
    $help .= "        # Generate SQL for all RepositoryEntity classes\n";
    $help .= "        repository.php schema --source=./src/Entity\n\n";
    $help .= "    This will:\n";
    $help .= "        1. Find {Table}RepositoryEntity.php file(s) in the source directory\n";
    $help .= "        2. Parse the class(es) to extract field definitions\n";
    $help .= "        3. Generate {table}.sql file(s) with CREATE TABLE statement(s)\n\n";
    $help .= "    Note: For audit log table schemas, use 'repository.php audit --schema'\n";
    
    echo $help;
}

/**
 * Handle schema command
 */
function handleSchemaCommand(array $argv): void
{
    $args = parseArguments($argv);
    
    // Check for help flags
    if ($args['help']) {
        printSchemaHelp();
        exit(0);
    }
    
    if (empty($args['source'])) {
        echo "Error: --source parameter is required\n";
        exit(1);
    }
    
    $sourceDir = $args['source'];
    
    if (!is_dir($sourceDir)) {
        echo "Error: Source directory not found: $sourceDir\n";
        exit(1);
    }
    
    // Determine which tables to process
    $entities = [];
    if (!empty($args['table'])) {
        $tableName = $args['table'];
        $entityFile = findRepositoryEntityFile($sourceDir, $tableName);
        if (!$entityFile) {
            echo "Error: Could not find RepositoryEntity file for table '$tableName'\n";
            echo "Expected file: " . toPascalCase($tableName) . "RepositoryEntity.php\n";
            exit(1);
        }
        $entities[$tableName] = $entityFile;
    } else {
        // Find all RepositoryEntity files
        echo "Finding all RepositoryEntity classes...\n";
        $entities = findAllRepositoryEntityFiles($sourceDir);
        if (empty($entities)) {
            echo "Error: No RepositoryEntity files found in $sourceDir\n";
            exit(1);
        }
        echo "Found " . count($entities) . " RepositoryEntity class(es)\n\n";
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($entities as $tableName => $entityFile) {
        echo "Processing: $tableName\n";
        echo "  Found RepositoryEntity file: $entityFile\n";
        
        try {
            // Parse the class
            $schema = parseRepositoryEntityClass($entityFile, $tableName);
            
            // Generate SQL
            $sql = generateCreateTableSql($schema, false);
            
            // Output SQL file
            $outputFile = combinePath($sourceDir, $tableName . '.sql');
            file_put_contents($outputFile, $sql);
            echo "  Generated: $outputFile\n";
            
            $successCount++;
        } catch (\Exception $e) {
            echo "  Error processing '$tableName': " . $e->getMessage() . "\n";
            $errorCount++;
        }
        
        echo "\n";
    }
    
    echo "Done! Successfully processed $successCount table(s)";
    if ($errorCount > 0) {
        echo ", $errorCount error(s)";
    }
    echo "\n";
}

