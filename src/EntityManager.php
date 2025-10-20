<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\RepositoryPolicy;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityMapperInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Tests\Integration\EntityErgonomicsTest;
use Tests\Integration\SomeEntity;

class EntityManager
{
    use Builder, Getter;

    private Database $database;
    private DataAccessPolicy $dataAccessPolicy;
    private RepositoryPolicy $repositoryPolicy;
    private $mapperSupplier;
    private bool $preventShutdown = false;

    // $tables[tableName] => EntityMapper
    /* @var \Amtgard\ActiveRecordOrm\Entity\EntityMapper[] */
    private array $mappers = [];

    // $entities[tableName][id] => entity
    /* @var \Amtgard\ActiveRecordOrm\Entity\Entity[][] */
    private array $entities = [];

    private function __construct() { }

    public static ?EntityManager $instance = null;

    public static function configure(EntityManager $entityManager) {
        Optional::ofNullable(static::$instance)
            ->orElseGet(function() use ($entityManager) {
                static::$instance = $entityManager;
                return static::$instance;
            });
    }

    public static function getManager(): EntityManager {
        return static::$instance;
    }

    public function getRepository(string $repository): EntityMapper {
        $table = TableFactory::build($this->database, $this->dataAccessPolicy, $repository::getTableName());
        return $repository::builder()->em($this)->entityInterface($repository::getEntityClass())->table($table)->build();
    }

    public function flushAll() {
        $mappers = $this->getMappers();
        foreach ($mappers as $mapperName => $mapper) {
            EntityManager::flushMapper($mapperName);
        }
    }

    public function flushMapper(string|EntityMapper $mapperRef) {
        $mapperRef = is_string($mapperRef) ? $mapperRef : $mapperRef->getName();
        Optional::ofNullable($this->getMapper($mapperRef))
            ->map(function($mapper) use ($mapperRef) {
                foreach ($this->getMapperEntities($mapperRef) as $entity) {
                    $policy = $this->getRepositoryPolicy();
                    $policy->flushEntity($mapper, $entity);
                }
            });
    }

    public function flush(Entity $entity) {
        $policy = $this->getRepositoryPolicy();
        $policy->flushEntity($entity->getMapper(), $entity);
    }

    public function clearAll() {
        foreach ($this->getEntities() as $mapperName => $entities) {
            EntityManager::clearMapper($mapperName);
        }
    }

    public function clearMapper(string $mapperName) {
        if (isset($this->entities[$mapperName])) {
            $this->entities = [];
        }
    }

    public function persist(string $mapperName, Entity $repositoryEntity): EntityInterface {
        return Optional::ofNullable($this->getEntity($mapperName, $repositoryEntity->getPrimaryKey()->getValue()))
            ->map(function($entity) use ($repositoryEntity) {
                return $entity;
            })
            ->orElseGet(function() use ($mapperName, $repositoryEntity) {
                $this->registerEntity($mapperName, $repositoryEntity);
                return $repositoryEntity;
            });
    }

    public function getEntity(string $tableName, int $entityId): ?EntityInterface {
        return Optional::ofNullable($this->getMapperEntities($tableName))
            ->map(function($entities) use ($entityId) {
                return $entities[$entityId];
            })
            ->orElse(null);
    }

    protected function registerEntity(string $mapperName, EntityInterface $entity) {
        $this->mapper($mapperName);
        $entityId = $entity->getPrimaryKey()->getValue();
        if (!isset($this->entities[$mapperName][$entityId])) {
            $this->entities[$mapperName][$entityId] = $entity;
        }
    }

    public function getMapperEntities($mapperName): array {
        return array_key_exists($mapperName, $this->entities) ? $this->entities[$mapperName] : [];
    }

    protected function getMapper($mapperName): ?EntityMapper {
        return array_key_exists($mapperName, $this->mappers) ? $this->mappers[$mapperName] : null;
    }

    protected function setMapper(EntityMapper $map) {
        $name = $map->getName();
        $this->mappers[$map->getName()] = $map;
    }

    protected function mapper(string $mapperName): EntityMapper {
        return Optional::ofNullable($this->getMapper($mapperName))
            ->map(fn($mapper) => $mapper)
            ->orElseGet(function() use ($mapperName) {
                $mapper = $this->getMapperSupplier()($mapperName);
                $this->setMapper($mapper);
                $mapper = $this->getMapper($mapperName);
                return $mapper;
            });
    }

    #[PostInit]
    private function init() {
        if (!Optional::ofNullable($this->mapperSupplier)->isPresent()) {
            $this->mapperSupplier = fn($mapperName) => EntityMapper::builder()
                ->table(TableFactory::build(
                    $this->getDatabase(),
                    $this->getDataAccessPolicy(),
                    $mapperName))
                ->name($mapperName)
                ->build();
        }
        if (!$this->preventShutdown) {
            register_shutdown_function(function() {
                $this->flushAll();
            });
        }
    }

}