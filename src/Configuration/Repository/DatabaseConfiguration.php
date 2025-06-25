<?php

namespace Amtgard\ActiveRecordOrm\Configuration\Repository;

use PDO;

define('DB_HOST', "DB_HOST");
define('DB_PORT', "DB_PORT");
define('DB_USER', "DB_USER");
define('DB_PASS', "DB_PASS");
define('DB_NAME', "DB_NAME");

class DatabaseConfiguration
{
    public static string $ENV_HOST_KEY = 'host';
    public static string $ENV_PORT_KEY = 'port';
    public static string $ENV_USER_KEY = 'user';
    public static string $ENV_PASSWORD_KEY = 'password';
    public static string $ENV_NAME_KEY = 'dbname';
    public static string $ENV_ERRMODE_KEY = 'errmode';
    public static string $ENV_OPTIONS_KEY = 'options';

    public static function fromEnvironment(): DatabaseConfiguration
    {
        return new self();
    }

    public function getConfig(): array
    {
        return [
            DatabaseConfiguration::$ENV_HOST_KEY => $_ENV[DB_HOST],
            DatabaseConfiguration::$ENV_PORT_KEY => $_ENV[DB_PORT],
            DatabaseConfiguration::$ENV_USER_KEY => $_ENV[DB_USER],
            DatabaseConfiguration::$ENV_PASSWORD_KEY => $_ENV[DB_PASS],
            DatabaseConfiguration::$ENV_NAME_KEY => $_ENV[DB_NAME],
            DatabaseConfiguration::$ENV_ERRMODE_KEY => PDO::ERRMODE_EXCEPTION,
            DatabaseConfiguration::$ENV_OPTIONS_KEY => [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
        ];
    }
}