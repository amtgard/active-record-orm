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

    public static function fromEnvironment(): DatabaseConfiguration
    {
        return new self();
    }

    public function getConfig(): array
    {
        return [
            'host' => $_ENV[DB_HOST],
            'port' => $_ENV[DB_PORT],
            'user' => $_ENV[DB_USER],
            'password' => $_ENV[DB_PASS],
            'dbname' => $_ENV[DB_NAME],
            'errmode' => PDO::ERRMODE_EXCEPTION,
            'options' => [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
        ];
    }
}