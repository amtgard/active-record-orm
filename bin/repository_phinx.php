#!/usr/bin/env php
<?php

require_once __DIR__ . '/repository_common.php';
requireAutoloader();

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
    $help .= "    repository.php phinx --source <directory> --file=<path>\n\n";
    $help .= "    Options:\n";
    $help .= "        --source <directory>  Directory containing Repository and RepositoryEntity classes\n";
    $help .= "                            Processes all PHP files that extend RepositoryEntity\n";
    $help .= "        --file=<path>        Path to Phinx migration file to generate (file must exist)\n";
    $help .= "        --help, -h           Show this help message\n\n";
    $help .= "    Examples:\n";
    $help .= "        # Generate Phinx migration for all RepositoryEntity classes\n";
    $help .= "        repository.php phinx --source ./src/Entity --file=./db/migrations/20251215143314_create_all_tables.php\n\n";
    $help .= "    This will:\n";
    $help .= "        1. Find all PHP files in the source directory that extend RepositoryEntity\n";
    $help .= "        2. Extract table names from the Repository classes (via EntityOf -> RepositoryOf)\n";
    $help .= "        3. Parse the class(es) to extract field definitions\n";
    $help .= "        4. Add create table code to the Phinx migration file\n\n";
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
    
    // Find all RepositoryEntity files
    echo "Finding all RepositoryEntity classes...\n";
    $entities = findAllRepositoryEntityFiles($sourceDir);
    if (empty($entities)) {
        echo "Error: No RepositoryEntity files found in $sourceDir\n";
        exit(1);
    }
    echo "Found " . count($entities) . " RepositoryEntity class(es)\n\n";
    
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
    
    // Parse existing table creations from the migration file
    $existingTables = [];
    if (!empty($existingMethodContent)) {
        // Find all table() calls and match them to their corresponding ->create();
        // Pattern: $this->table("table_name") followed by column definitions ending with ->create();
        $offset = 0;
        while (($pos = strpos($existingMethodContent, '$this->table(', $offset)) !== false) {
            // Extract table name
            $tableStart = $pos;
            $afterTable = substr($existingMethodContent, $pos);
            
            // Match: $this->table("table_name")
            if (preg_match('/\$this->table\(["\']([^"\']+)["\']\)/s', $afterTable, $tableMatch)) {
                $tableName = $tableMatch[1];
                
                // Find the matching ->create(); that ends this table block
                // Look for ->create(); after the table() call
                $searchStart = $pos + strlen($tableMatch[0]);
                $remaining = substr($existingMethodContent, $searchStart);
                
                // Match everything up to and including ->create();
                if (preg_match('/.*?->create\(\);/s', $remaining, $createMatch)) {
                    $blockEnd = $searchStart + strlen($createMatch[0]);
                    $blockCode = substr($existingMethodContent, $tableStart, $blockEnd - $tableStart);
                    
                    $existingTables[$tableName] = [
                        'start' => $tableStart,
                        'end' => $blockEnd,
                        'code' => $blockCode
                    ];
                    
                    $offset = $blockEnd;
                } else {
                    // No matching ->create(); found, skip this one
                    $offset = $pos + 1;
                }
            } else {
                $offset = $pos + 1;
            }
        }
    }
    
    // Generate table creation code for all tables
    $tableCodes = [];
    $tablesToAdd = [];
    $tablesToReplace = [];
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    foreach ($entities as $tableName => $entityFile) {
        echo "Processing: $tableName\n";
        echo "  Found RepositoryEntity file: $entityFile\n";
        
        try {
            // Parse the class
            $schema = parseRepositoryEntityClass($entityFile, $tableName);
            
            // Generate table creation code
            $tableCode = generatePhinxTableCode($schema, false);
            
            // Check if this table already exists in the migration
            if (isset($existingTables[$tableName])) {
                echo "  Warning: Table '$tableName' already exists in the migration file.\n";
                if (confirm("  Replace existing table creation for '$tableName'?", true)) {
                    $tablesToReplace[$tableName] = $tableCode;
                    echo "  Will replace table creation code for: $tableName\n";
                    $successCount++;
                } else {
                    echo "  Skipping table '$tableName' (keeping existing code)\n";
                    $skippedCount++;
                }
            } else {
                $tablesToAdd[$tableName] = $tableCode;
                echo "  Added table creation code for: $tableName\n";
                $successCount++;
            }
        } catch (\Exception $e) {
            echo "  Error processing '$tableName': " . $e->getMessage() . "\n";
            $errorCount++;
        }
        
        echo "\n";
    }
    
    // Build the final method content
    $allTableCode = '';
    
    if (!empty($existingMethodContent)) {
        // Start with existing content
        $result = $existingMethodContent;
        $offset = 0;
        
        // Replace existing table blocks that were confirmed for replacement
        // Process in reverse order to maintain correct positions
        $replacements = [];
        foreach ($tablesToReplace as $tableName => $newCode) {
            if (isset($existingTables[$tableName])) {
                $replacements[] = [
                    'table' => $tableName,
                    'start' => $existingTables[$tableName]['start'],
                    'end' => $existingTables[$tableName]['end'],
                    'newCode' => $newCode
                ];
            }
        }
        
        // Sort by start position in reverse order
        usort($replacements, function($a, $b) {
            return $b['start'] - $a['start'];
        });
        
        // Replace from end to beginning to maintain positions
        foreach ($replacements as $replacement) {
            $before = substr($result, 0, $replacement['start']);
            $after = substr($result, $replacement['end']);
            $result = $before . $replacement['newCode'] . $after;
        }
        
        // Remove tables that were replaced from existingTables so they're not duplicated
        foreach (array_keys($tablesToReplace) as $tableName) {
            unset($existingTables[$tableName]);
        }
        
        // Add new tables
        if (!empty($tablesToAdd)) {
            $newTableCode = implode("\n", array_values($tablesToAdd));
            if (!empty($result)) {
                $result .= "\n" . $newTableCode;
            } else {
                $result = $newTableCode;
            }
        }
        
        $allTableCode = $result;
    } else {
        // No existing content, just add all new tables
        $allTableCode = implode("\n", array_values($tablesToAdd));
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
    echo "Successfully processed $successCount table(s)";
    if ($skippedCount > 0) {
        echo ", $skippedCount skipped";
    }
    if ($errorCount > 0) {
        echo ", $errorCount error(s)";
    }
    echo "\n";
}

