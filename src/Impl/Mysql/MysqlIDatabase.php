<?php

namespace Amtgard\ActiveRecordOrm\Implementation\Mysql;

use Amtgard\ActiveRecordOrm\Interface\Database\IDatabase;
use Amtgard\ActiveRecordOrm\Interface\Database\IDatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Interface\Entity\RecordSet;
use PDO;

class MysqlIDatabase implements IDatabase
{

    private PDO $__dbh;

    private array $__fields;

    public static function fromConfig(IDatabaseConfiguration $configuration): IDatabase
    {
        return new self($configuration);
    }

    private function __construct(IDatabaseConfiguration $configuration) {
        $config = $configuration->getConfig();
        $host = $config['host'];
        $port = $config['port'];
        $dbname = $config['dbname'];
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
        return new RecordSet($query);
    }

    private function bindParams(\PDOStatement &$query): void {
        foreach ($this->__fields as $field => $value) {
            $query->bindValue(":$field", $value);
        }
    }

    public function clear(): void
    {
        $this->__fields = [];
    }

}