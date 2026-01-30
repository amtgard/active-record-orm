<?php

namespace Amtgard\ActiveRecordOrm\Entity\Repository;

use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Exception\AmtgardOrmException;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityMapperInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Interface\QueryableInterface;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Amtgard\Traits\Builder\ToBuilder;

abstract class Repository implements ActiveRecordTableInterface, EntityRepositoryInterface, EntityMapperInterface, QueryableInterface
{
    use Builder, ToBuilder;

    protected EntityMapper $auditEntityMapper;
    protected string $repositoryEntityClass;
    protected string $tableName;
    protected EntityManager $entityManager;
    protected EntityMapper $entityMapper;
    protected EntityFieldMap $entityFieldMap;

    #[PostInit]
    protected function postInit() {
        $this->initRepositoryOf();
        $repositoryEntityClass = $this->repositoryEntityClass;
        $this->entityFieldMap = $repositoryEntityClass::buildEntityFieldMap();
    }

    public function getDatabase() {
        return $this->entityMapper->getDatabase();
    }

    public function getTable(): TableInterface {
        return $this->entityMapper->getTable();
    }

    public function getChanges(): array
    {
        return $this->entityMapper->getChanges();
    }

    protected function initRepositoryOf()
    {
        $reflection = new \ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(RepositoryOf::class);
        if (count($attributes) === 0) {
            return null;
        }

        $attribute = $attributes[0];
        $arguments = $attribute->getArguments();

        if (count($arguments) !== 2) {
            throw new \RuntimeException(sprintf(
                'RepositoryOf attribute on %s must declare a table name and repository entity class.',
                static::class
            ));
        }

        $tableName = $arguments[0] ?? null;
        $repositoryEntityClass = $arguments[1] ?? null;

        if (!is_subclass_of($repositoryEntityClass, EntityInterface::class)) {
            throw new \RuntimeException(sprintf(
                'RepositoryOf repository entity class %s must implement %s.',
                $repositoryEntityClass,
                EntityInterface::class
            ));
        }

        $this->repositoryEntityClass = $repositoryEntityClass;
        $this->tableName = $tableName;
    }

    public function __set(string $name, $value): void
    {
        $this->entityMapper->$name = $value;
    }

    public function __get(string $name)
    {
        return $this->entityMapper->$name ?? null;
    }

    public function clear(): void
    {
        $this->entityMapper->clear();
    }

    public function orderBy(string $fieldName, OrderBy $orderBy): void
    {
        $this->entityMapper->orderBy($fieldName, $orderBy);
    }

    public function select(mixed $fieldNameOrSet): void
    {
        $this->entityMapper->select($fieldNameOrSet);
    }

    public function find(): int
    {
        return $this->entityMapper->find();
    }

    function delete(?EntityInterface $entity): void
    {
        $this->entityMapper->delete($entity);
    }

    public function count(string $countAlias = 'row_count'): int
    {
        return $this->entityMapper->count($countAlias);
    }

    public function page(int $size = 10, int $page = 0): ActiveRecordTableInterface
    {
        return $this->entityMapper->page($page, $size);
    }

    public function limit(int $offset, ?int $rowCount = null): void
    {
        $this->entityMapper->limit($offset, $rowCount);
    }

    public function size(): int
    {
        return $this->entityMapper->size();
    }

    public function next(): bool
    {
        return $this->entityMapper->next();
    }

    public function hasActiveRecord(): bool
    {
        return $this->entityMapper->hasActiveRecord();
    }

    function getEntity(): ?EntityInterface
    {
        return $this->entityMapper->getEntity();
    }

    function fetch($primaryKeyValue = null): ?EntityInterface
    {
        $repositoryEntityClass = $this->repositoryEntityClass;
        $entity = $this->entityMapper->fetch($primaryKeyValue);
        return $repositoryEntityClass::toRepositoryEntity($entity);
    }

    function fetchBy(string $field, $value): ?EntityInterface
    {
        $repositoryEntityClass = $this->repositoryEntityClass;
        $repositoryEntityField = $this->entityFieldMap->getField($field)->getSource() ?? $field;
        return $repositoryEntityClass::toRepositoryEntity($this->entityMapper->fetchBy($repositoryEntityField, $value));
    }

    function persist(EntityInterface $entity): EntityInterface
    {
        $repositoryEntityClass = $this->repositoryEntityClass;
        return $repositoryEntityClass::toRepositoryEntity($this->entityMapper->persist($entity->getInternalEntity()));
    }

    function newRepositoryEntity(): EntityInterface {
        $this->clear();
        $repositoryEntityClass = $this->repositoryEntityClass;
        return $repositoryEntityClass::toRepositoryEntity($this->emptyEntity());
    }

    protected function emptyEntity(): EntityInterface {
        $table = $this->entityMapper->getTable();
        return Entity::builder()
            ->resultSet($table->getResultSet())
            ->schema($table->getTableSchema())
            ->mapper($this->entityMapper)
            ->build();
    }

    function persistAsEntity(): EntityInterface
    {
        $this->clear();
        return $this->entityMapper->persistAsEntity();
    }

    static function getTableName()
    {
        throw new AmtgardOrmException("You must implement getTableName() in your Repository definition.");
    }

    public static function getEntityClass()
    {
        throw new AmtgardOrmException("You must implement getEntityClass() in your Repository definition.");
    }

    function query(string $sql): void
    {
        $this->entityMapper->query($sql);
    }

    function execute(): int
    {
        return $this->entityMapper->execute();
    }

}