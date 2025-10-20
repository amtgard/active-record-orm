<?php

namespace Amtgard\ActiveRecordOrm\Entity;

use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityMapperInterface;
use Amtgard\ActiveRecordOrm\Interface\QueryableInterface;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;

class EntityMapper implements ActiveRecordTableInterface, EntityMapperInterface, QueryableInterface
{
    use Builder;

    private const QUERY_MODE = 'query';
    private const TABLE_MODE = 'table';

    protected ?EntityManager $em = null;
    protected Table $table;
    private Database $database;

    private string $mode = self::TABLE_MODE;
    private string $querySql;
    private ?RecordSet $recordSet;
    private $entityResultSetBuilder = null;
    protected string $name;
    private array $changes = [];

    public function getName(): string {
        return $this->name;
    }

    public function __get(string $name) {
        $primaryKeyField = $this->table->getTableSchema()->getPrimaryKey()->getName();
        return Optional::ofNullable($this->getEm()->getEntity($this->table->getName(), $this->table->$primaryKeyField))
            ->map(fn($entity) => $entity->$name)
            ->orElseGet(fn() => $this->table->$name);
    }

    public function __set(string $name, $value): void
    {
        if ($this->mode === self::QUERY_MODE) {
            $this->database->$name = $value;
        } else {
            $this->table->$name = $value;
        }
        $this->changes[$name] = $value;
    }

    public function getEntity(): EntityInterface {
        if ($this->mode === self::QUERY_MODE) {
            $resultSet = call_user_func($this->entityResultSetBuilder);
        } else {
            $resultSet = $this->table->getResultSet();
        }

        $entity = Entity::builder()
            ->resultSet($resultSet)
            ->schema($this->table->getTableSchema())
            ->mapper($this)
            ->build();

        return $this->getEm()->register($this->table->getName(), $entity);
    }

    public function fetch($primaryKeyValue = null): EntityInterface {
        return Optional::ofNullable($primaryKeyValue)
            ->map(function($primaryKeyValue) {
                $primaryKeyField = $this->table->getTableSchema()->getPrimaryKey()->getName();
                $this->table->clear();
                $this->table->$primaryKeyField = $primaryKeyValue;
                $this->table->find();
                $this->table->next();
                return $this->getEntity();
            })
            ->orElseGet(function() {
                $this->table->find();
                $this->next();
                return $this->getEntity();
            });
    }

    public function fetchBy(string $field, $value): EntityInterface {
        $this->table->clear();
        $this->table->$field = $value;
        return $this->fetch();
    }

    public function persist(EntityInterface $entity): EntityInterface {
        return EntityManager::getManager()->register($this->getName(), $entity);
    }

    public function createInternalEntity(): EntityInterface {
        $this->table->save();
        $this->table->find();
        $this->table->next();
        return Entity::builder()
            ->resultSet($this->table->getResultSet())
            ->schema($this->table->getTableSchema())
            ->mapper($this)
            ->build();
    }

    public function createEntity(): EntityInterface {
        return $this->getEm()->register($this->table->getName(), $this->createInternalEntity());
    }

    public function query($sql): void {
        $this->querySql = $sql;
        $this->mode = self::QUERY_MODE;
    }

    public function execute(): int {
        $this->recordSet = $this->database->execute($this->querySql);
        return $this->recordSet->size();
    }

    public function clear(): void
    {
        $this->recordSet = null;
        $this->mode = self::TABLE_MODE;
        $this->table->clear();
        $this->changes = [];
        if (Optional::ofNullable($this->database)->isPresent()) {
            $this->database->clear();
        }
    }

    public function orderBy(string $fieldName, OrderBy $orderBy): void
    {
        $this->table->orderBy($fieldName, $orderBy);
    }

    public function select(mixed $fieldNameOrSet): void
    {
        $this->table->select($fieldNameOrSet);
    }

    public function find(): int
    {
        return $this->table->find();
    }

    public function count(string $countAlias = 'row_count'): int
    {
        return $this->table->count($countAlias);
    }

    public function page(int $size = 10, int $page = 0): ActiveRecordTableInterface
    {
        return $this->table->page($size, $page);
    }

    public function limit(int $offset, ?int $rowCount = null): void
    {
        $this->table->limit($offset, $rowCount);
    }

    public function size(): int
    {
        return $this->table->size();
    }

    public function next(): bool
    {
        if ($this->mode === self::QUERY_MODE) {
            return $this->recordSet->next();
        } else {
            return $this->table->next();
        }
    }

    public function hasActiveRecord(): bool
    {
        return $this->table->hasActiveRecord();
    }

    public function getTable(): Table {
        return $this->table;
    }

    private function getEm(): EntityManager {
        return Optional::ofNullable($this->em)
            ->orElseGet(function() {
                $this->em = EntityManager::getManager();
                return $this->em;
            });
    }

    #[PostInit]
    private function postInit() {
        if (!isset($this->table)) {
            throw new \Exception('A table must be set for EntityOf.');
        }
        if (!isset($this->database)) {
            $this->database = $this->table->getDatabase();
        }
        $this->name = $this->table->getName();
        if (!isset($this->entityResultSetBuilder)) {
            $this->entityResultSetBuilder = fn() => ResultSet::builder()
                ->recordSet($this->recordSet)
                ->schema($this->table->getTableSchema())
                ->fieldSet($this->table->getFieldSet())
                ->build();
        }
    }
}