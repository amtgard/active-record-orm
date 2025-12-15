<?php

namespace Amtgard\ActiveRecordOrm\Trait;

use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\Traits\Builder\PostInit;

trait RepositoryTrait
{
    protected string $repositoryEntityClass;
    protected string $tableName;
    protected EntityManager $entityManager;
    protected EntityMapper $entityMapper;
    protected $entityMapInfo;

    #[PostInit]
    protected function postInit() {
        $this->initRepositoryOf();
        $repositoryEntityClass = $this->repositoryEntityClass;
        $this->entityMapInfo = $repositoryEntityClass::buildEntityMapInfo();
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
        return $repositoryEntityClass::toRepositoryEntity($this->entityMapper->fetch($primaryKeyValue));
    }

    function fetchBy(string $field, $value): ?EntityInterface
    {
        $repositoryEntityClass = $this->repositoryEntityClass;
        $repositoryEntityField = $this->entityMapInfo[$field]['source'] ?? $field;
        return $repositoryEntityClass::toRepositoryEntity($this->entityMapper->fetchBy($repositoryEntityField, $value));
    }

    function persist(EntityInterface $entity): EntityInterface
    {
        $repositoryEntityClass = $this->repositoryEntityClass;
        return $repositoryEntityClass::toRepositoryEntity($this->entityMapper->persist($entity->getInternalEntity()));
    }

    function createEntity(): EntityInterface
    {
        $this->clear();
        $repositoryEntityClass = $this->repositoryEntityClass;
        return $repositoryEntityClass::toRepositoryEntity($this->entityMapper->createEntity());
    }

    function createMapperEntity(): EntityInterface {
        $this->clear();
        return $this->entityMapper->createInternalEntity();
    }

    function createInternalEntity(): EntityInterface
    {
        $this->clear();
        return $this->entityMapper->createInternalEntity();
    }

    static function getTableName()
    {
        return static::getTableName();
    }

    public static function getEntityClass()
    {
        return static::getEntityClass();
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