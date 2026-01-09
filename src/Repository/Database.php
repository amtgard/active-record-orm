<?php

namespace Amtgard\ActiveRecordOrm\Repository;

use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Configuration\Repository\PdoProviderInterface;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Schema\Schema;
use Amtgard\Traits\Builder\Builder;
use PDO;

class Database
{
    use Builder;

    private PDO $__dbh;

    private array $__fields = [];

    private string $__databaseName;

    private PdoProviderInterface $__pdo;

    private function __construct()
    {
    }

    public static function fromProvider(PdoProviderInterface $pdoProvider): Database
    {
        return Database::builder()
            ->__databaseName($pdoProvider->getDatabaseName())
            ->__dbh($pdoProvider->getPdo())
            ->__fields([])
            ->build();
    }

    public function __set(string $field, string|int|bool $value)
    {
        $this->__fields[$field] = $value;
    }

    public function executeQuery(Query $query): RecordSet
    {
        $this->__fields = $query->getParams();
        return $this->execute($query->getSql());
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

    private function bindParams(\PDOStatement &$query): array
    {
        $bound = [];
        foreach ($this->__fields as $field => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }
            $bound[] = $query->bindValue(":$field", $value, $type);
        }
        return $bound;
    }

    public function fqTableName($tableName): string
    {
        return $this->__databaseName . '.' . $tableName;
    }

    public function clear(): void
    {
        $this->__fields = [];
    }

    public function getLastInsertId(): string
    {
        return $this->__dbh->lastInsertId();
    }
}