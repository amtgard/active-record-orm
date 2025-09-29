<?php

namespace Tests\Unit\Entity\Policy;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class UncachedPolicyTest extends AmtgardTestCase
{
    private UncachedPolicy $uncachedPolicy;
    private EntityMapper $mockEntityMapper;
    private Entity $mockEntity;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockEntityMapper = Phake::mock(EntityMapper::class);
        $this->mockEntity = Phake::mock(Entity::class);
        
        $this->uncachedPolicy = UncachedPolicy::builder()->build();
    }

    public function testFlushEntity_delegatesToEntityFlush(): void
    {
        $this->uncachedPolicy->flushEntity($this->mockEntityMapper, $this->mockEntity);
        
        Phake::verify($this->mockEntity)->flush($this->mockEntityMapper);
    }
}
