<?php

namespace Tests\Unit\Entity\Repository;

use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\PHPUnit\AmtgardTestCase;

class ConcreteRepositoryEntityForTest extends RepositoryEntity
{
    public static function toRepositoryEntity(?EntityInterface $entity): ?EntityInterface
    {
        return $entity;
    }
}

class RepositoryEntityTest extends AmtgardTestCase
{
    public function testRepositoryEntityCanBeInstantiated(): void
    {
        $entity = ConcreteRepositoryEntityForTest::builder()
            ->build();

        self::assertInstanceOf(RepositoryEntity::class, $entity);
        self::assertInstanceOf(EntityInterface::class, $entity);
    }

    public function testRepositoryEntityImplementsEntityInterface(): void
    {
        $interfaces = class_implements(RepositoryEntity::class);

        self::assertContains(EntityInterface::class, $interfaces);
    }

    public function testRepositoryEntityUsesRepositoryEntityTrait(): void
    {
        $traits = class_uses(RepositoryEntity::class);

        self::assertContains(\Amtgard\ActiveRecordOrm\Trait\RepositoryEntityTrait::class, $traits);
    }
}
