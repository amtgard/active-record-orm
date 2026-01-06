#!/usr/bin/env php
<?php

require_once __DIR__ . '/repository_common.php';
requireAutoloader();

/**
 * Print help/usage information
 */
function printHelp(): void
{
    $help = "Amtgard Active Record ORM - Repository Generator Tool\n\n";
    $help .= "USAGE:\n";
    $help .= "    repository.php <command> [options]\n\n";
    $help .= "COMMANDS:\n";
    $help .= "    classes     Generate Repository and RepositoryEntity classes from MySQL schema\n";
    $help .= "    schema      Generate MySQL CREATE TABLE SQL from RepositoryEntity classes\n";
    $help .= "    phinx       Generate Phinx migration from RepositoryEntity classes\n";
    $help .= "    audit       Generate audit-related classes, schemas, or migrations\n";
    $help .= "    help        Show this help message (also --help, -h)\n\n";
    $help .= "CLASSES COMMAND:\n";
    $help .= "    Generate Repository and RepositoryEntity classes by inspecting an existing MySQL table.\n\n";
    $help .= "    repository.php classes --env=<path> --table=<table> --out-dir=<path>\n\n";
    $help .= "    Options:\n";
    $help .= "        --env=<path>         Path to .env file containing database connection strings\n";
    $help .= "        --table=<table>      Table name in snake_case (e.g., 'user_profiles')\n";
    $help .= "        --out-dir=<path>     Output directory for generated PHP files\n\n";
    $help .= "    Example:\n";
    $help .= "        repository.php classes --env=./.env --table=user_profiles --out-dir=./src/Entity\n\n";
    $help .= "SCHEMA COMMAND:\n";
    $help .= "    Generate MySQL CREATE TABLE SQL from existing RepositoryEntity class definitions.\n\n";
    $help .= "    repository.php schema --source=<path> --table=<table>\n\n";
    $help .= "    Options:\n";
    $help .= "        --source=<path>      Directory containing Repository and RepositoryEntity classes\n";
    $help .= "        --table=<table>      Table name in snake_case (e.g., 'user_profiles')\n\n";
    $help .= "    Example:\n";
    $help .= "        repository.php schema --source=./src/Entity --table=user_profiles\n\n";
    $help .= "PHINX COMMAND:\n";
    $help .= "    Generate Phinx migration from existing RepositoryEntity class definitions.\n\n";
    $help .= "    repository.php phinx --source=<path> --table=<table> --file=<path>\n\n";
    $help .= "    Options:\n";
    $help .= "        --source=<path>      Directory containing Repository and RepositoryEntity classes\n";
    $help .= "        --table=<table>      Table name in snake_case (e.g., 'user_profiles')\n";
    $help .= "        --file=<path>        Path to Phinx migration file to generate (file must exist)\n\n";
    $help .= "    Example:\n";
    $help .= "        repository.php phinx --source=./src/Entity --table=user_profiles --file=./db/migrations/20251215143314_create_user_profiles.php\n\n";
    $help .= "AUDIT COMMAND:\n";
    $help .= "    Generate audit-related classes, schemas, or migrations.\n\n";
    $help .= "    repository.php audit --classes --env=<path> --table=<table> --out-dir=<path>\n";
    $help .= "    repository.php audit --schema --source=<path> --table=<table>\n";
    $help .= "    repository.php audit --phinx --source=<path> --table=<table> --file=<path>\n\n";
    $help .= "    Use 'repository.php audit --help' for detailed information.\n\n";
    $help .= "HELP FLAGS:\n";
    $help .= "    --help, -h              Show this help message\n\n";
    $help .= "EXAMPLES:\n";
    $help .= "    # Generate classes from database\n";
    $help .= "    repository.php classes --env=.env --table=items --out-dir=./src\n\n";
    $help .= "    # Generate SQL from classes\n";
    $help .= "    repository.php schema --source=./src --table=items\n\n";
    $help .= "    # Show help\n";
    $help .= "    repository.php help\n";
    $help .= "    repository.php --help\n";
    $help .= "    repository.php -h\n";
    
    echo $help;
}

/**
 * Main execution
 */
function main(array $argv): void
{
    $args = parseArguments($argv);
    
    // Check for help flags without a command (general help)
    if (($args['help'] && empty($args['command'])) || $args['command'] === 'help' || empty($args['command'])) {
        printHelp();
        exit(0);
    }
    
    $command = $args['command'];
    $scriptPath = __DIR__ . "/repository_{$command}.php";
    
    if (!file_exists($scriptPath)) {
        echo "Error: Unknown command '$command'\n\n";
        printHelp();
        exit(1);
    }
    
    // Include the command script
    require $scriptPath;
    
    // Call the command handler function
    $handlerFunction = "handle" . ucfirst($command) . "Command";
    if (function_exists($handlerFunction)) {
        $handlerFunction($argv);
    } else {
        echo "Error: Command handler not found for '$command'\n";
        exit(1);
    }
}

main($argv);
