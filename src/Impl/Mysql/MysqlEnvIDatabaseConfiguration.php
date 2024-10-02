<?php

namespace Amtgard\ActiveRecordOrm\Implementation\Mysql;

use Amtgard\ActiveRecordOrm\Interface\Database\IDatabaseConfiguration;
use PDO;

class MysqlEnvIDatabaseConfiguration implements IDatabaseConfiguration
{

    public static function fromEnvironment(): IDatabaseConfiguration
    {
        return new self();
    }

    public function getConfig(): array
    {
        return [
            'host' => $_ENV["DBHOST"],
            'port' => $_ENV["DBPORT"],
            'user' => $_ENV["DBUSER"],
            'password' => $_ENV["DBPASS"],
            'dbname' => $_ENV["DBNAME"],
            'errmode' => PDO::ERRMODE_EXCEPTION,
            'options' => [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
        ];
    }
}