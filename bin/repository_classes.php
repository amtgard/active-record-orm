#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/repository_common.php';

use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Dotenv\Dotenv;

/**
 * Generate Repository class code
 */
function generateRepositoryClass(string $tableName, string $entityClassName, string $namespace = ''): string
{
    $repositoryClassName = toPascalCase($tableName) . 'Repository';
    $nsPrefix = $namespace ? "namespace $namespace;\n\n" : '';
    
    return <<<PHP
<?php

{$nsPrefix}use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;

#[RepositoryOf("$tableName", $entityClassName::class)]
class $repositoryClassName extends Repository
{
    public static function getTableName()
    {
        return '$tableName';
    }

    public static function getEntityClass()
    {
        return $entityClassName::class;
    }
}
PHP;
}

/**
 * Generate RepositoryEntity class code
 */
function generateRepositoryEntityClass(string $tableName, array $fields, string $primaryKey, string $repositoryClassName, string $namespace = ''): string
{
    $entityClassName = toPascalCase($tableName) . 'RepositoryEntity';
    $nsPrefix = $namespace ? "namespace $namespace;\n\n" : '';
    
    $uses = "use Amtgard\ActiveRecordOrm\Attribute\EntityOf;\n";
    $uses .= "use Amtgard\ActiveRecordOrm\Attribute\Field;\n";
    $uses .= "use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;\n";
    $uses .= "use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;\n";
    $uses .= "use Amtgard\Traits\Builder\Builder;\n";
    $uses .= "use Amtgard\Traits\Builder\Data;\n";
    $uses .= "use Amtgard\Traits\Builder\ToBuilder;";
    
    $properties = [];
    foreach ($fields as $field) {
        $fieldName = $field->getName();
        $propertyName = toCamelCase($fieldName);
        $phpType = fieldTypeToPhpType($field->getType(), $field->getNullable());
        
        $attributes = [];
        if ($fieldName === $primaryKey) {
            $attributes[] = '#[PrimaryKey]';
        } else {
            $attributes[] = "#[Field('$fieldName')]";
        }
        
        $attributesStr = implode("\n    ", $attributes);
        $properties[] = "    $attributesStr\n    private $phpType \$$propertyName;";
    }
    
    $propertiesStr = implode("\n\n", $properties);
    
    return <<<PHP
<?php

{$nsPrefix}$uses

#[EntityOf($repositoryClassName::class)]
class $entityClassName extends RepositoryEntity
{
    use Builder, ToBuilder, Data;

$propertiesStr
}
PHP;
}

/**
 * Print help for classes command
 */
function printClassesHelp(): void
{
    $help = "CLASSES COMMAND:\n";
    $help .= "    Generate Repository and RepositoryEntity classes by inspecting an existing MySQL table.\n\n";
    $help .= "    repository.php classes --env=<path> [--table=<table>] --out-dir=<path>\n\n";
    $help .= "    Options:\n";
    $help .= "        --env=<path>         Path to .env file or directory containing .env file\n";
    $help .= "        --table=<table>      Optional: Table name in snake_case (e.g., 'user_profiles')\n";
    $help .= "                            If omitted, generates classes for all tables in the database\n";
    $help .= "        --out-dir=<path>     Output directory for generated PHP files\n";
    $help .= "        --help, -h           Show this help message\n\n";
    $help .= "    Examples:\n";
    $help .= "        # Generate classes for a specific table\n";
    $help .= "        repository.php classes --env=./.env --table=user_profiles --out-dir=./src/Entity\n\n";
    $help .= "        # Generate classes for all tables\n";
    $help .= "        repository.php classes --env=./.env --out-dir=./src/Entity\n\n";
    $help .= "    This will:\n";
    $help .= "        1. Connect to MySQL database using credentials from .env file\n";
    $help .= "        2. Inspect the specified table schema\n";
    $help .= "        3. Generate {Table}Repository.php class\n";
    $help .= "        4. Generate {Table}RepositoryEntity.php class with all table fields\n";
    
    echo $help;
}

/**
 * Handle classes command
 */
function handleClassesCommand(array $argv): void
{
    $args = parseArguments($argv);
    
    // Check for help flags
    if ($args['help']) {
        printClassesHelp();
        exit(0);
    }
    
    if (empty($args['env'])) {
        echo "Error: --env parameter is required\n";
        exit(1);
    }
    
    if (empty($args['out-dir'])) {
        echo "Error: --out-dir parameter is required\n";
        exit(1);
    }
    
    // Load environment variables
    $envPath = normalizeEnvPath($args['env']);
    if (!file_exists($envPath)) {
        echo "Error: Environment file not found: $envPath\n";
        exit(1);
    }
    
    $dotenv = Dotenv::createImmutable(dirname($envPath), basename($envPath));
    $dotenv->safeLoad();
    
    // Connect to database
    $config = DatabaseConfiguration::fromEnvironment();
    $provider = MysqlPdoProvider::fromConfiguration($config);
    $db = Database::fromProvider($provider);
    
    // Create output directory if it doesn't exist
    $outDir = $args['out-dir'];
    if (!is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }
    
    // Determine which tables to process
    $tables = [];
    if (!empty($args['table'])) {
        $tables = [$args['table']];
    } else {
        // Get all tables from database (exclusions are handled in getAllTables)
        echo "Fetching all tables from database...\n";
        $tables = getAllTables($db);
        if (empty($tables)) {
            echo "Error: No tables found in database\n";
            exit(1);
        }
            $exclusions = getExcludedTables();
            $excludedCount = count($exclusions['exact']) + count($exclusions['patterns']);
            if ($excludedCount > 0) {
                echo "Found " . count($tables) . " table(s) (excluding $excludedCount pattern(s) from .exclusions)\n\n";
            } else {
                echo "Found " . count($tables) . " table(s)\n\n";
            }
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($tables as $tableName) {
        echo "Processing table: $tableName\n";
        
        try {
            // Get schema
            $schema = getTableSchema($db, $tableName);
            
            if (empty($schema['fields'])) {
                echo "  Warning: Table '$tableName' has no fields, skipping\n";
                $errorCount++;
                continue;
            }
            
            if (empty($schema['primaryKey'])) {
                echo "  Warning: Table '$tableName' has no primary key\n";
            }
            
            // Generate class names
            $repositoryClassName = toPascalCase($tableName) . 'Repository';
            $entityClassName = toPascalCase($tableName) . 'RepositoryEntity';
            
            // Generate Repository class
            $repositoryCode = generateRepositoryClass($tableName, $entityClassName);
            $repositoryFile = combinePath($outDir, $repositoryClassName . '.php');
            file_put_contents($repositoryFile, $repositoryCode);
            echo "  Generated: $repositoryFile\n";
            
            // Generate RepositoryEntity class
            $entityCode = generateRepositoryEntityClass(
                $tableName,
                $schema['fields'],
                $schema['primaryKey'],
                $repositoryClassName
            );
            $entityFile = combinePath($outDir, $entityClassName . '.php');
            file_put_contents($entityFile, $entityCode);
            echo "  Generated: $entityFile\n";
            
            $successCount++;
        } catch (\Exception $e) {
            echo "  Error processing table '$tableName': " . $e->getMessage() . "\n";
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

