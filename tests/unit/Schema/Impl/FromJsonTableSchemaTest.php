<?php

namespace Tests\Unit\Schema\Impl;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\FromJsonTableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Tests\util\Constants;

class FromJsonTableSchemaTest extends AmtgardTestCase
{
    public function testComparisonWithUncachedTableSchema() {
        $database = Phake::mock(Database::class);
        $schema = FromJsonTableSchema::builder()
                    ->jsonDefinition(Constants::$JSON_ENCODED_INTEG_SCHEMA)
                    ->tableName("integ")
                    ->database($database)
                    ->build();

        self::assertEquals(13, count($schema->getFields()));
        self::assertEquals("id", $schema->getPrimaryKey()->getName());
    }
}