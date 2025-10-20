<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\RepositoryPolicy;
use Amtgard\ActiveRecordOrm\Exception\AmtgardOrmException;
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

    // $mappers[tableName] => EntityMapper
    /* @var \Amtgard\ActiveRecordOrm\Entity\EntityMapper[] */
    private array $mappers = [];

    // $entities[tableName][id] => EntityInterface
    /* @var \Amtgard\ActiveRecordOrm\Entity\Entity[][] */
    private array $entities = [];

    // $repositories[tableName] => Repository
    /* @var \Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface[] */
    private array $repositories = [];

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

    public function getRepository(string $repository): EntityRepositoryInterface {
        if (!class_exists($repository)) {
            throw new AmtgardOrmException(sprintf('Repository class "%s" does not exist.', $repository));
        }
        if (!in_array(EntityRepositoryInterface::class, class_implements($repository))) {
            throw new AmtgardOrmException(sprintf('Repository class "%s" must implement EntityRepositoryInterface.', $repository));
        }
        $this->repositories[$repository::getTableName()] = Optional::ofNullable($this->repositories[$repository::getTableName()])
            ->orElseGet(function() use ($repository) {
                $mapper = $this->mapper($repository::getTableName());
                return $repository::builder()->entityManager($this)->tableName($repository::getTableName())->entityMapper($mapper)->build();
            });
        return $this->repositories[$repository::getTableName()];
    }

    public function persist(string|EntityMapper|EntityInterface|null $persistable = null) {
        if (is_null($persistable)) {
            $this->persistAll();
        } else if ($persistable instanceof EntityInterface) {
            $this->persistEntity($persistable);
        } else {
            $this->persistMapper($persistable);
        }
    }

    public function persistAll() {
        $mappers = $this->getMappers();
        foreach ($mappers as $mapperName => $mapper) {
            EntityManager::persist($mapperName);
        }
    }

    public function persistEntity(EntityInterface $entity) {
        $policy = $this->getRepositoryPolicy();
        $policy->persist($entity->getMapper(), $entity);
    }

    public function persistMapper(string|EntityMapper $entityMapper) {
        $entityMapper = is_string($entityMapper) ? $entityMapper : $entityMapper->getName();
        Optional::ofNullable($this->getMapper($entityMapper))
            ->map(function ($mapper) use ($entityMapper) {
                foreach ($this->getMapperEntities($entityMapper) as $entity) {
                    $policy = $this->getRepositoryPolicy();
                    $policy->persist($mapper, $entity);
                }
            });
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

    public function register(string $mapperName, EntityInterface $repositoryEntity): EntityInterface {
        $primaryKeyValue = $repositoryEntity->getPrimaryKey()->getValue();
        $entity = $this->getEntity($mapperName, $primaryKeyValue);
        return Optional::ofNullable($entity)
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

    public function getMapper($mapperName): ?EntityMapper {
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
                $this->persistAll();
            });
        }
    }

}