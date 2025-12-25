<?php

namespace Tests\Unit\Entity\Policy;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\EventualConsistencyPolicy;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Psr\SimpleCache\CacheInterface;

class EventualConsistencyPolicyTest extends AmtgardTestCase
{
    private EventualConsistencyPolicy $eventualConsistencyPolicy;
    private EntityMapper $mockEntityMapper;
    private Entity $mockEntity;
    private CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockEntityMapper = Phake::mock(EntityMapper::class);
        $this->mockEntity = Phake::mock(Entity::class);
        $this->mockCache = Phake::mock(CacheInterface::class);
        
        $this->eventualConsistencyPolicy = EventualConsistencyPolicy::builder()
            ->cache($this->mockCache)
            ->build();
    }

    public function testFlushEntity_delegatesToEntityFlush(): void
    {
        $this->eventualConsistencyPolicy->persist($this->mockEntityMapper, $this->mockEntity);
        
        Phake::verify($this->mockEntity)->persist($this->mockEntityMapper);
    }
}

