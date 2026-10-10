<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\CacheSubject;
use Amtgard\PHPUnit\AmtgardTestCase;

class CacheSubjectTest extends AmtgardTestCase
{
    public function testBuilder_assignsDatabaseKindAndKey(): void
    {
        $subject = CacheSubject::builder()
            ->databaseName('ork')
            ->entryKind('schema')
            ->keyId('users')
            ->build();

        self::assertSame('ork', $subject->getDatabaseName());
        self::assertSame('schema', $subject->getEntryKind());
        self::assertSame('users', $subject->getKeyId());
    }
}
