#!/usr/bin/env php
<?php

/**
 * Find and include the Composer autoloader
 * Searches parent directories to find vendor/autoload.php
 */
function requireAutoloader(): void
{
    $candidates = [
        __DIR__ . '/../vendor/autoload.php',  // When run from bin/
        __DIR__ . '/../../vendor/autoload.php', // When installed as dependency
        __DIR__ . '/../../../vendor/autoload.php', // Deeper nesting
    ];
    
    // Also search parent directories dynamically
    $dir = __DIR__;
    $maxDepth = 5; // Prevent infinite loops
    $depth = 0;
    
    while ($depth < $maxDepth) {
        $autoloader = $dir . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
            return;
        }
        
        $parent = dirname($dir);
        if ($parent === $dir) {
            // Reached filesystem root
            break;
        }
        $dir = $parent;
        $depth++;
    }
    
    // If not found, try the candidates
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            require_once $candidate;
            return;
        }
    }
    
    throw new \RuntimeException(
        'Could not find Composer autoloader. Make sure you have run "composer install".'
    );
}

use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;

/**
 * Convert snake_case to PascalCase
 */
function toPascalCase(string $str): string
{
    return str_replace('_', '', ucwords($str, '_'));
}

/**
 * Convert snake_case to camelCase
 */
function toCamelCase(string $str): string
{
    return lcfirst(toPascalCase($str));
}

/**
 * Convert camelCase to snake_case
 */
function toSnakeCase(string $str): string
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
}

/**
 * Combine path segments, handling trailing slashes correctly
 */
function combinePath(string ...$segments): string
{
    $path = '';
    foreach ($segments as $segment) {
        if (empty($segment)) {
            continue;
        }
        
        // Remove trailing slashes from current path
        $path = rtrim($path, '/\\');
        
        // Remove leading slashes from segment (except for first segment if it's absolute)
        if (!empty($path) || (strlen($segment) > 0 && ($segment[0] !== '/' && $segment[0] !== '\\'))) {
            $segment = ltrim($segment, '/\\');
        }
        
        if (!empty($path)) {
            $path .= DIRECTORY_SEPARATOR;
        }
        
        $path .= $segment;
    }
    
    return $path;
}

/**
 * Normalize env file path - if directory is provided, append .env
 */
function normalizeEnvPath(string $path): string
{
    // Remove trailing slashes
    $path = rtrim($path, '/\\');
    
    // Check if it's a directory
    if (is_dir($path)) {
        // If it's a directory, append .env
        return combinePath($path, '.env');
    }
    
    // If it's already a file, return as-is
    return $path;
}

/**
 * Map FieldType to PHP type hint
 */
function fieldTypeToPhpType(FieldType $fieldType, bool $nullable): string
{
    $type = match ($fieldType) {
        FieldType::INTEGER => 'int',
        FieldType::STRING, FieldType::LOB, FieldType::BINARY, FieldType::ENUM, FieldType::UUID, FieldType::DECIMAL => 'string',
        FieldType::DATETIME => '\\DateTime',
        FieldType::DOUBLE => 'float',
        FieldType::BOOL => 'bool',
        default => 'mixed',
    };
    
    return $nullable ? "?$type" : $type;
}

/**
 * Map PHP type hint to MySQL type
 */
function phpTypeToMySqlType(string $phpType, bool $nullable): string
{
    // Remove nullable prefix
    $type = ltrim($phpType, '?');
    
    // Remove namespace prefix if present
    $type = str_replace('\\DateTime', 'DateTime', $type);
    $type = str_replace('\\', '', $type);
    
    return match ($type) {
        'int' => 'INT',
        'string' => 'VARCHAR(255)',
        'float' => 'DOUBLE',
        'bool' => 'TINYINT(1)',
        'DateTime' => 'DATETIME',
        'mixed' => 'TEXT',
        default => 'VARCHAR(255)',
    };
}

/**
 * Map PHP type hint to Phinx column type
 */
function phpTypeToPhinxType(string $phpType): string
{
    // Remove nullable prefix
    $type = ltrim($phpType, '?');
    
    // Remove namespace prefix if present
    $type = str_replace('\\DateTime', 'DateTime', $type);
    $type = str_replace('\\', '', $type);
    
    return match ($type) {
        'int' => 'integer',
        'string' => 'string',
        'float' => 'float',
        'bool' => 'boolean',
        'DateTime' => 'datetime',
        'mixed' => 'text',
        default => 'string',
    };
}

/**
 * Get Phinx column options from field information
 */
function getPhinxColumnOptions(array $field, bool $isPrimaryKey): array
{
    $options = [];
    
    // Nullable option
    $options['null'] = $field['nullable'];
    
    // Limit for string types
    if (str_contains($field['phpType'], 'string') && !str_contains($field['phpType'], '?')) {
        $options['limit'] = 255;
    }
    
    // Identity (auto-increment) for integer primary keys
    if ($isPrimaryKey && str_contains($field['phpType'], 'int') && !$field['nullable']) {
        $options['identity'] = true;
    }
    
    return $options;
}

/**
 * Prompt user for confirmation (defaults to No)
 * Returns true if user confirms, false otherwise
 */
function confirm(string $message, bool $defaultNo = true): bool
{
    // Standard convention: default shown in uppercase
    // [y/N] means default is No, [Y/n] means default is Yes
    $prompt = $message . ' [' . ($defaultNo ? 'y/N' : 'Y/n') . ']: ';
    
    echo $prompt;
    $input = trim(fgets(STDIN));
    
    if (empty($input)) {
        return !$defaultNo;
    }
    
    $input = strtoupper($input);
    return $input === 'Y' || $input === 'YES';
}

/**
 * Parse command line arguments
 */
function parseArguments(array $argv): array
{
    $args = [
        'command' => null,
        'env' => null,
        'table' => null,
        'out-dir' => null,
        'source' => null,
        'file' => null,
        'classes' => false,
        'schema' => false,
        'phinx' => false,
        'migrate' => false,
        'help' => false,
    ];
    
    $currentKey = null;
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        // Check for help flags
        if ($arg === '--help' || $arg === '-h' || $arg === 'help') {
            $args['help'] = true;
            continue;
        }
        
        if (strpos($arg, '--') === 0) {
            // Handle --key=value format
            if (strpos($arg, '=') !== false) {
                [$key, $value] = explode('=', substr($arg, 2), 2);
                $args[$key] = $value;
            } else {
                $key = substr($arg, 2);
                // For boolean flags (like --classes, --schema, --phinx, --migrate, --help), set to true
                // Check if it's a known boolean flag
                if (in_array($key, ['classes', 'schema', 'phinx', 'migrate', 'help'])) {
                    $args[$key] = true;
                } else {
                    $currentKey = $key;
                }
            }
        } elseif ($currentKey !== null) {
            $args[$currentKey] = $arg;
            $currentKey = null;
        } elseif ($args['command'] === null) {
            $args['command'] = $arg;
        }
    }
    
    return $args;
}

/**
 * Get table schema from MySQL
 */
function getTableSchema(Database $db, string $tableName): array
{
    $db->clear();
    $result = $db->execute("DESCRIBE `$tableName`");
    
    $fields = [];
    $primaryKey = null;
    
    while ($result->next()) {
        $fieldDef = FieldDefinition::fromDescribeTable($result);
        $fields[] = $fieldDef;
        
        // Check if this is the primary key
        if ($result->Key === 'PRI') {
            $primaryKey = $fieldDef->getName();
        }
    }
    
    return [
        'fields' => $fields,
        'primaryKey' => $primaryKey,
    ];
}

/**
 * Get excluded table names and patterns from .exclusions file
 * Returns array with 'exact' and 'patterns' keys
 */
function getExcludedTables(): array
{
    $exclusionsFile = __DIR__ . '/.exclusions';
    if (!file_exists($exclusionsFile)) {
        return ['exact' => [], 'patterns' => []];
    }
    
    $exact = [];
    $patterns = [];
    $lines = file($exclusionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments
        if (strpos($line, '#') === 0) {
            continue;
        }
        if (!empty($line)) {
            // Check if it's a glob pattern (contains * or ?)
            if (strpos($line, '*') !== false || strpos($line, '?') !== false) {
                $patterns[] = $line;
            } else {
                $exact[] = $line;
            }
        }
    }
    
    return ['exact' => $exact, 'patterns' => $patterns];
}

/**
 * Check if a table name matches any exclusion pattern
 */
function isTableExcluded(string $tableName): bool
{
    $exclusions = getExcludedTables();
    
    // Check exact matches
    if (in_array($tableName, $exclusions['exact'])) {
        return true;
    }
    
    // Check glob patterns
    foreach ($exclusions['patterns'] as $pattern) {
        // Convert glob pattern to regex
        // First escape special regex characters, then replace glob wildcards
        $escaped = preg_quote($pattern, '/');
        // Replace escaped \* and \? back to regex equivalents
        $regex = '/^' . str_replace(
            ['\\*', '\\?'],
            ['.*', '.'],
            $escaped
        ) . '$/';
        
        if (preg_match($regex, $tableName)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get all table names from MySQL database, excluding tables from .exclusions file
 */
function getAllTables(Database $db): array
{
    $db->clear();
    $result = $db->execute("SHOW TABLES");
    
    $tables = [];
    
    while ($result->next()) {
        // SHOW TABLES returns a single column with different names depending on database
        // Get the record array and extract the first value
        $record = $result->getRecord();
        if ($record !== null) {
            // Get the first value from the record array
            $tableName = reset($record);
            
            // Skip excluded tables (checks both exact matches and glob patterns)
            if (!isTableExcluded($tableName)) {
                $tables[] = $tableName;
            }
        }
    }
    
    return $tables;
}

/**
 * Find all RepositoryEntity files in a directory
 */
function findAllRepositoryEntityFiles(string $sourceDir): array
{
    if (!is_dir($sourceDir)) {
        return [];
    }
    
    // Find all PHP files in the directory
    $files = glob(combinePath($sourceDir, '*.php'));
    
    // First pass: Load all PHP files to ensure dependencies are available
    foreach ($files as $file) {
        require_once $file;
    }
    
    // Second pass: Extract table names from RepositoryEntity files
    $entities = [];
    foreach ($files as $file) {
        $tableName = extractTableNameFromRepositoryEntityFile($file);
        if ($tableName) {
            $entities[$tableName] = $file;
        }
    }
    
    return $entities;
}

/**
 * Extract table name from a RepositoryEntity file by inspecting the class
 * Returns the table name if successful, null otherwise
 */
function extractTableNameFromRepositoryEntityFile(string $filePath): ?string
{
    try {
        // Read the file content to extract namespace and class name
        $content = file_get_contents($filePath);
        
        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }
        
        // Extract class name
        if (!preg_match('/class\s+(\w+)/', $content, $matches)) {
            return null; // Skip files without a class definition
        }
        
        $className = $matches[1];
        $fullClassName = $namespace ? "$namespace\\$className" : $className;
        
        // Load the class file
        require_once $filePath;
        
        // Check if class exists and extends RepositoryEntity
        if (!class_exists($fullClassName)) {
            return null;
        }
        
        $reflection = new \ReflectionClass($fullClassName);
        
        // Check if this class extends RepositoryEntity
        if (!$reflection->isSubclassOf(\Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity::class)) {
            return null;
        }
        
        // Get the EntityOf attribute to find the Repository class
        $entityOfAttributes = $reflection->getAttributes(\Amtgard\ActiveRecordOrm\Attribute\EntityOf::class);
        if (empty($entityOfAttributes)) {
            return null; // Skip if no EntityOf attribute
        }
        
        $entityOfAttribute = $entityOfAttributes[0];
        $entityOfArgs = $entityOfAttribute->getArguments();
        $repositoryClass = $entityOfArgs[0] ?? null;
        
        if (!$repositoryClass) {
            return null;
        }
        
        // Try to find and load the Repository class file if it's not already loaded
        if (!class_exists($repositoryClass)) {
            // Extract the class name from the fully qualified class name
            $repositoryClassName = is_string($repositoryClass) ? $repositoryClass : null;
            if (!$repositoryClassName) {
                return null;
            }
            
            // Extract short class name (handle both namespaced and non-namespaced)
            $parts = explode('\\', $repositoryClassName);
            $shortClassName = end($parts);
            
            // Try to find the Repository file in the same directory
            $dir = dirname($filePath);
            $possibleFiles = [
                combinePath($dir, $shortClassName . '.php'),
                combinePath($dir, $shortClassName . 'Repository.php'),
            ];
            
            // Also try without "Repository" suffix if it's already there
            if (str_ends_with($shortClassName, 'Repository')) {
                $baseName = substr($shortClassName, 0, -10);
                $possibleFiles[] = combinePath($dir, $baseName . '.php');
                $possibleFiles[] = combinePath($dir, $baseName . 'Repository.php');
            }
            
            $found = false;
            foreach ($possibleFiles as $possibleFile) {
                if (file_exists($possibleFile)) {
                    require_once $possibleFile;
                    $found = true;
                    break;
                }
            }
            
            // If still not found, try loading all PHP files in the directory
            if (!$found && !class_exists($repositoryClass)) {
                $phpFiles = glob(combinePath($dir, '*.php'));
                foreach ($phpFiles as $phpFile) {
                    if ($phpFile !== $filePath) {
                        require_once $phpFile;
                        if (class_exists($repositoryClass)) {
                            $found = true;
                            break;
                        }
                    }
                }
            }
        }
        
        if (!class_exists($repositoryClass)) {
            return null; // Skip if Repository class still not found after trying to load it
        }
        
        // Get the RepositoryOf attribute from the Repository class to extract table name
        $repositoryReflection = new \ReflectionClass($repositoryClass);
        $repositoryOfAttributes = $repositoryReflection->getAttributes(\Amtgard\ActiveRecordOrm\Attribute\RepositoryOf::class);
        
        if (empty($repositoryOfAttributes)) {
            return null; // Skip if no RepositoryOf attribute
        }
        
        $repositoryOfAttribute = $repositoryOfAttributes[0];
        $repositoryOfArgs = $repositoryOfAttribute->getArguments();
        $tableName = $repositoryOfArgs[0] ?? null;
        
        return $tableName;
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Find RepositoryEntity class file
 */
function findRepositoryEntityFile(string $sourceDir, string $tableName): ?string
{
    $entityClassName = toPascalCase($tableName) . 'RepositoryEntity';
    $entityFile = combinePath($sourceDir, $entityClassName . '.php');
    
    if (file_exists($entityFile)) {
        return $entityFile;
    }
    
    // Try to find it by scanning directory
    if (is_dir($sourceDir)) {
        $files = glob(combinePath($sourceDir, '*RepositoryEntity.php'));
        foreach ($files as $file) {
            $className = basename($file, '.php');
            if ($className === $entityClassName) {
                return $file;
            }
        }
    }
    
    return null;
}

/**
 * Parse RepositoryEntity class and extract field information
 */
function parseRepositoryEntityClass(string $filePath, string $tableName): array
{
    // Load the file to get the class
    $content = file_get_contents($filePath);
    
    // Extract namespace
    $namespace = null;
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
        $namespace = $matches[1];
    }
    
    // Extract class name - try to find any class in the file
    $entityClassName = null;
    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        $entityClassName = $matches[1];
    }
    
    // Fallback to expected naming convention if no class found
    if (!$entityClassName) {
        $entityClassName = toPascalCase($tableName) . 'RepositoryEntity';
    }
    
    $fullClassName = $namespace ? "$namespace\\$entityClassName" : $entityClassName;
    
    // Include the file to load the class
    require_once $filePath;
    
    // Check if class exists (with or without namespace)
    if (!class_exists($fullClassName) && !class_exists($entityClassName)) {
        throw new \RuntimeException("Could not load class: $fullClassName (or $entityClassName). Make sure the file is properly formatted.");
    }
    
    // Use the correct class name
    $actualClassName = class_exists($fullClassName) ? $fullClassName : $entityClassName;
    
    $reflection = new \ReflectionClass($actualClassName);
    $fields = [];
    $primaryKey = null;
    
    foreach ($reflection->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED) as $property) {
        $propertyName = $property->getName();
        $propertyType = $property->getType();
        $isNullable = $propertyType && $propertyType->allowsNull();
        
        $dbFieldName = null;
        $isPrimaryKey = false;
        
        foreach ($property->getAttributes() as $attribute) {
            $attributeName = $attribute->getName();
            
            if ($attributeName === PrimaryKey::class) {
                $isPrimaryKey = true;
                $args = $attribute->getArguments();
                // PrimaryKey can have optional name argument, otherwise use property name
                if (!empty($args) && isset($args[0])) {
                    $dbFieldName = $args[0];
                } elseif ($dbFieldName === null) {
                    // If no Field attribute found yet, convert property name to snake_case
                    $dbFieldName = toSnakeCase($propertyName);
                }
            } elseif ($attributeName === Field::class) {
                $args = $attribute->getArguments();
                $dbFieldName = $args[0] ?? toSnakeCase($propertyName);
            }
        }
        
        // If we found a PrimaryKey but no Field attribute, use snake_case of property name
        if ($isPrimaryKey && $dbFieldName === null) {
            $dbFieldName = toSnakeCase($propertyName);
        }
        
        if ($dbFieldName !== null) {
            $phpType = 'mixed';
            if ($propertyType) {
                $phpType = $propertyType->getName();
                // Remove namespace prefix but keep the type
                $phpType = str_replace('\\DateTime', 'DateTime', $phpType);
            }
            
            $fields[] = [
                'dbName' => $dbFieldName,
                'propertyName' => $propertyName,
                'phpType' => $phpType,
                'nullable' => $isNullable,
                'isPrimaryKey' => $isPrimaryKey,
            ];
            
            if ($isPrimaryKey) {
                $primaryKey = $dbFieldName;
            }
        }
    }
    
    return [
        'fields' => $fields,
        'primaryKey' => $primaryKey,
        'tableName' => $tableName,
    ];
}

