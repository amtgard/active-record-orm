<?php

namespace Amtgard\ActiveRecordOrm\Configuration\Database;

use Amtgard\ActiveRecordOrm\RecordSet;
use PDO;

class Database
{
    private PDO $__dbh;

    private array $__fields;

    private string $__databaseName;

    public static function fromConfig(DatabaseConfiguration $configuration): Database
    {
        return new self($configuration);
    }

    private function __construct(DatabaseConfiguration $configuration) {
        $config = $configuration->getConfig();
        $host = $config['host'];
        $port = $config['port'];
        $this->__databaseName = $dbname = $config['dbname'];
        $user = $config['user'];
        $password = $config['password'];
        $errMode = $config['errmode'];
        $options = $config['options'];
        $this->__dbh = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $password, $options);
        $this->__dbh->setAttribute(PDO::ATTR_ERRMODE, $errMode);
        $this->__fields = [];
    }

    public function __set(String $field, String|int|bool $value) {
        $this->__fields[$field] = $value;
    }

    public function execute(string $sql): RecordSet
    {
        $query = $this->__dbh->prepare($sql);
        if (count($this->__fields) > 0) {
            $this->bindParams($query);
        }
        $query->execute();
        return new RecordSet\PdoRecordSet($query);
    }

    private function bindParams(\PDOStatement &$query): array {
        foreach ($this->__fields as $field => $value) {
            $bound[] = $query->bindValue(":$field", $value);
        }
        return $bound;
    }

    public function fqTableName($tableName): string {
        return $this->__databaseName . '.' . $tableName;
    }

    public function clear(): void
    {
        $this->__fields = [];
    }
}