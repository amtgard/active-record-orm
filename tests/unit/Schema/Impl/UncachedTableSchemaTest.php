<?php

namespace Tests\Unit\Schema\Impl;

use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\Impl\UncachedTableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Tests\util\Constants;

class UncachedTableSchemaTest extends AmtgardTestCase
{
    public function testUncachedTableSchemaRunsPostInit() {
        $database = Phake::mock(Database::class);
        $__statement = Phake::mock(\PDOStatement::class);
        $recordSet = new RecordSet\PdoRecordSet($__statement);

        $integ_schema = json_decode(Constants::$DESCRIBE_TABLE_INTEG, true);
        $phakeWhenRef = Phake::when($__statement)->fetch();
        foreach ($integ_schema as $column) {
            $phakeWhenRef = $phakeWhenRef->thenReturn($column);
        }
        $phakeWhenRef->thenReturn(false);

        Phake::when($database)->execute("describe integ")->thenReturn($recordSet);

        self::assertDoesNotThrow(function() use (&$schema, $database) {
            $schema = UncachedTableSchema::builder()
                ->tableName("integ")
                ->database($database)
                ->build();
        });
        self::assertEquals(13, count($schema->getFields()));
        self::assertEquals("id", $schema->getPrimaryKey()->getName());
        Phake::verify($__statement, Phake::times(14))->fetch();
    }
}