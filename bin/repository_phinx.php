#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/repository_common.php';

/**
 * Generate Phinx migration table creation code (without class wrapper)
 */
function generatePhinxTableCode(array $schema, bool $isAuditLog = false): string
{
    $tableName = $schema['tableName'];
    $fields = $schema['fields'];
    $primaryKey = $schema['primaryKey'];
    
    if ($isAuditLog) {
        $tableName = $tableName . '_audit_log';
    }
    
    $code = "        \$this->table(\"$tableName\")\n";
    
    $columnCalls = [];
    
    if ($isAuditLog) {
        // Add audit log primary key first (identity => true automatically creates primary key)
        $columnCalls[] = "            ->addColumn('id',  'integer', ['null' => false, 'identity' => true])";
        // Add audit log specific fields
        $columnCalls[] = "            ->addColumn('record_id',  'integer', ['null' => false])";
        $columnCalls[] = "            ->addColumn('log_datetime',  'datetime', ['null' => false])";
        $columnCalls[] = "            ->addColumn('fields',  'text', ['limit' => 16535, 'null' => true])";
        $columnCalls[] = "            ->addColumn('action',  'enum', ['values' => [\"insert\", \"update\", \"delete\"]])";
    }
    
    // Add regular fields (excluding primary key for audit log)
    foreach ($fields as $field) {
        if ($isAuditLog && $field['isPrimaryKey']) {
            // Skip primary key field in audit log - use record_id instead
            continue;
        }
        
        $dbName = $field['dbName'];
        $phinxType = phpTypeToPhinxType($field['phpType']);
        $options = getPhinxColumnOptions($field, $field['isPrimaryKey'] && !$isAuditLog);
        
        // Format options array
        $optionsParts = ["'null' => " . ($options['null'] ? 'true' : 'false')];
        if (isset($options['limit'])) {
            $optionsParts[] = "'limit' => {$options['limit']}";
        }
        if (isset($options['identity']) && $options['identity']) {
            $optionsParts[] = "'identity' => true";
        }
        $optionsStr = '[' . implode(', ', $optionsParts) . ']';
        
        $columnCalls[] = "            ->addColumn('$dbName',  '$phinxType', $optionsStr)";
    }
    
    $code .= implode("\n", $columnCalls);
    
    // Add primary key if specified and not auto-increment (only for non-audit tables)
    // (identity => true automatically creates primary key for audit log 'id' field)
    if (!$isAuditLog && $primaryKey) {
        $hasAutoIncrement = false;
        foreach ($fields as $field) {
            if ($field['dbName'] === $primaryKey && 
                $field['isPrimaryKey'] && 
                str_contains($field['phpType'], 'int') && 
                !$field['nullable']) {
                $hasAutoIncrement = true;
                break;
            }
        }
        
        if (!$hasAutoIncrement) {
            $code .= "\n            ->addIndex(['$primaryKey'], ['unique' => true, 'name' => 'PRIMARY'])";
        }
    }
    
    $code .= "\n            ->create();\n";
    
    return $code;
}

/**
 * Generate Phinx migration class
 * Made public for use by audit command
 */
function generatePhinxMigration(array $schema, string $migrationFile, bool $isAuditLog = false): string
{
    // Extract class name from filename (e.g., 20251215143314_audit_table_test_tables.php -> AuditTableTestTables)
    $baseName = basename($migrationFile, '.php');
    $parts = explode('_', $baseName, 2);
    $className = isset($parts[1]) ? toPascalCase($parts[1]) : toPascalCase($schema['tableName']);
    
    $code = "<?php\n\n";
    $code .= "declare(strict_types=1);\n\n";
    $code .= "use Phinx\Migration\AbstractMigration;\n\n";
    $code .= "final class $className extends AbstractMigration\n";
    $code .= "{\n";
    $code .= "    /**\n";
    $code .= "     * Change Method.\n";
    $code .= "     *\n";
    $code .= "     * Write your reversible migrations using this method.\n";
    $code .= "     *\n";
    $code .= "     * More information on writing migrations is available here:\n";
    $code .= "     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method\n";
    $code .= "     *\n";
    $code .= "     * Remember to call \"create()\" or \"update()\" and NOT \"save()\" when working\n";
    $code .= "     * with the Table class.\n";
    $code .= "     */\n";
    $code .= "    public function change(): void\n";
    $code .= "    {\n";
    $code .= generatePhinxTableCode($schema, $isAuditLog);
    $code .= "    }\n";
    $code .= "}\n";
    
    return $code;
}

/**
 * Print help for phinx command
 */
function printPhinxHelp(): void
{
    $help = "PHINX COMMAND:\n";
    $help .= "    Generate Phinx migration from existing RepositoryEntity class definitions.\n\n";
    $help .= "    repository.php phinx --source=<path> [--table=<table>] --file=<path>\n\n";
    $help .= "    Options:\n";
    $help .= "        --source=<path>      Directory containing Repository and RepositoryEntity classes\n";
    $help .= "        --table=<table>      Optional: Table name in snake_case (e.g., 'user_profiles')\n";
    $help .= "                            If omitted, adds CREATE TABLE for all RepositoryEntity classes\n";
    $help .= "        --file=<path>        Path to Phinx migration file to generate (file must exist)\n";
    $help .= "        --help, -h           Show this help message\n\n";
    $help .= "    Examples:\n";
    $help .= "        # Generate Phinx migration for a specific table\n";
    $help .= "        repository.php phinx --source=./src/Entity --table=user_profiles --file=./db/migrations/20251215143314_create_user_profiles.php\n\n";
    $help .= "        # Generate Phinx migration for all RepositoryEntity classes\n";
    $help .= "        repository.php phinx --source=./src/Entity --file=./db/migrations/20251215143314_create_all_tables.php\n\n";
    $help .= "    This will:\n";
    $help .= "        1. Find {Table}RepositoryEntity.php file(s) in the source directory\n";
    $help .= "        2. Parse the class(es) to extract field definitions\n";
    $help .= "        3. Add create table code to the Phinx migration file\n\n";
    $help .= "    Note: For audit log table migrations, use 'repository.php audit --phinx'\n";
    
    echo $help;
}

/**
 * Handle phinx command
 */
function handlePhinxCommand(array $argv): void
{
    $args = parseArguments($argv);
    
    // Check for help flags
    if ($args['help']) {
        printPhinxHelp();
        exit(0);
    }
    
    if (empty($args['source'])) {
        echo "Error: --source parameter is required\n";
        exit(1);
    }
    
    if (empty($args['file'])) {
        echo "Error: --file parameter is required\n";
        exit(1);
    }
    
    $sourceDir = $args['source'];
    $phinxFile = $args['file'];
    
    if (!is_dir($sourceDir)) {
        echo "Error: Source directory not found: $sourceDir\n";
        exit(1);
    }
    
    if (!file_exists($phinxFile)) {
        echo "Error: Phinx migration file not found: $phinxFile\n";
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
    
    // Read existing migration file to extract class structure
    $existingContent = file_get_contents($phinxFile);
    
    // Extract class name from existing file
    if (preg_match('/class\s+(\w+)\s+extends\s+AbstractMigration/', $existingContent, $matches)) {
        $className = $matches[1];
    } else {
        // Fallback: extract from filename
        $baseName = basename($phinxFile, '.php');
        $parts = explode('_', $baseName, 2);
        $className = isset($parts[1]) ? toPascalCase($parts[1]) : 'Migration';
    }
    
    // Extract change() method content if it exists
    $existingMethodContent = '';
    if (preg_match('/public function change\(\): void\s*\{([^}]*)\}/s', $existingContent, $matches)) {
        $existingMethodContent = trim($matches[1]);
    }
    
    // Generate table creation code for all tables
    $tableCodes = [];
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($entities as $tableName => $entityFile) {
        echo "Processing: $tableName\n";
        echo "  Found RepositoryEntity file: $entityFile\n";
        
        try {
            // Parse the class
            $schema = parseRepositoryEntityClass($entityFile, $tableName);
            
            // Generate table creation code
            $tableCode = generatePhinxTableCode($schema, false);
            $tableCodes[] = $tableCode;
            
            echo "  Added table creation code for: $tableName\n";
            $successCount++;
        } catch (\Exception $e) {
            echo "  Error processing '$tableName': " . $e->getMessage() . "\n";
            $errorCount++;
        }
        
        echo "\n";
    }
    
    // Combine existing content with new table codes
    $allTableCode = implode("\n", $tableCodes);
    if (!empty($existingMethodContent)) {
        $allTableCode = $existingMethodContent . "\n" . $allTableCode;
    }
    
    // Generate complete migration file
    $migrationCode = "<?php\n\n";
    $migrationCode .= "declare(strict_types=1);\n\n";
    $migrationCode .= "use Phinx\Migration\AbstractMigration;\n\n";
    $migrationCode .= "final class $className extends AbstractMigration\n";
    $migrationCode .= "{\n";
    $migrationCode .= "    /**\n";
    $migrationCode .= "     * Change Method.\n";
    $migrationCode .= "     *\n";
    $migrationCode .= "     * Write your reversible migrations using this method.\n";
    $migrationCode .= "     *\n";
    $migrationCode .= "     * More information on writing migrations is available here:\n";
    $migrationCode .= "     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method\n";
    $migrationCode .= "     *\n";
    $migrationCode .= "     * Remember to call \"create()\" or \"update()\" and NOT \"save()\" when working\n";
    $migrationCode .= "     * with the Table class.\n";
    $migrationCode .= "     */\n";
    $migrationCode .= "    public function change(): void\n";
    $migrationCode .= "    {\n";
    $migrationCode .= $allTableCode;
    $migrationCode .= "    }\n";
    $migrationCode .= "}\n";
    
    file_put_contents($phinxFile, $migrationCode);
    echo "Generated Phinx migration: $phinxFile\n";
    echo "Successfully added $successCount table(s)";
    if ($errorCount > 0) {
        echo ", $errorCount error(s)";
    }
    echo "\n";
}

