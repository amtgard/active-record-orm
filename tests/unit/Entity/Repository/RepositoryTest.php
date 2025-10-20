<?php

namespace Tests\Unit\Entity\Repository;

use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\PHPUnit\AmtgardTestCase;

#[RepositoryOf("test_table", ConcreteRepositoryEntity::class)]
class ConcreteRepository extends Repository implements EntityRepositoryInterface
{
    public static function getTableName()
    {
        return 'test_table';
    }

    public static function getEntityClass()
    {
        return ConcreteRepositoryEntity::class;
    }
}

class ConcreteRepositoryEntity extends \Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity
{
    public static function toRepositoryEntity(\Amtgard\ActiveRecordOrm\Interface\EntityInterface $entity): \Amtgard\ActiveRecordOrm\Interface\EntityInterface
    {
        return $entity;
    }
}

class RepositoryTest extends AmtgardTestCase
{
    public function testRepositoryCanBeInstantiated(): void
    {
        $mockEntityMapper = \Phake::mock(EntityMapper::class);
        $mockEntityManager = \Phake::mock(EntityManager::class);

        $repository = ConcreteRepository::builder()
            ->entityManager($mockEntityManager)
            ->tableName('test_table')
            ->entityMapper($mockEntityMapper)
            ->build();

        self::assertInstanceOf(Repository::class, $repository);
        self::assertInstanceOf(EntityRepositoryInterface::class, $repository);
    }

    public function testRepositoryImplementsInterfaces(): void
    {
        $interfaces = class_implements(Repository::class);

        self::assertContains(\Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface::class, $interfaces);
        self::assertContains(\Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface::class, $interfaces);
        self::assertContains(\Amtgard\ActiveRecordOrm\Interface\EntityMapperInterface::class, $interfaces);
        self::assertContains(\Amtgard\ActiveRecordOrm\Interface\QueryableInterface::class, $interfaces);
    }

    public function testRepositoryUsesRepositoryTrait(): void
    {
        $traits = class_uses(Repository::class);

        self::assertContains(\Amtgard\ActiveRecordOrm\Trait\RepositoryTrait::class, $traits);
    }
}
