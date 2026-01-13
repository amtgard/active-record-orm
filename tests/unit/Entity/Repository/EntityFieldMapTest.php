<?php

namespace Tests\Unit\Entity\Repository;

use Amtgard\ActiveRecordOrm\Entity\Repository\EntityFieldMap;
use Amtgard\ActiveRecordOrm\Entity\Repository\MappedInfo;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class EntityFieldMapTest extends AmtgardTestCase
{
    private EntityFieldMap $entityFieldMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityFieldMap = new EntityFieldMap();
    }

    public function testGetFieldDirectMatch(): void
    {
        $mappedInfo = Phake::mock(MappedInfo::class);
        $mappedInfo->source = 'source_field';
        $this->entityFieldMap->setField('test_field', $mappedInfo);

        $result = $this->entityFieldMap->getField('test_field');

        $this->assertSame($mappedInfo, $result);
    }

    public function testGetFieldAliasedMatch(): void
    {
        $mappedInfo = Phake::mock(MappedInfo::class);
        $mappedInfo->source = 'source_field';
        $this->entityFieldMap->setField('test_field', $mappedInfo);

        $result = $this->entityFieldMap->getField('source_field');

        $this->assertSame($mappedInfo, $result);
    }

    public function testGetFieldNoMatch(): void
    {
        $result = $this->entityFieldMap->getField('non_existent_field');

        $this->assertNull($result);
    }
}
