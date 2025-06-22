<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use PHPUnit\Framework\TestCase;
use Tests\util\Constants;

class TableSchemaTest extends AmtgardTestCase
{
    public function testAbstractFeatures() {
        $database = Phake::mock(Database::class);
        $fields = [ 'a' => FieldDefinition::builder()->name('a')->build(), 'pk' => FieldDefinition::builder()->name('pk')->build() ];

        $schema = null;

        self::assertDoesNotThrow(function() use (&$schema, $database, $fields) {
            $schema = TableSchemaImpl::builder()
                ->tableName("name")
                ->database($database)
                ->fields($fields)
                ->primaryKey($fields['pk'])
                ->build();
        });

        self::assertEquals($fields, $schema->getFields());
        self::assertEquals($database, $schema->getDatabase());
        self::assertEquals("name", $schema->getTableName());
        self::assertEquals($fields['a'], $schema->getField('a'));
        self::assertEquals($fields['pk'], $schema->getPrimaryKey());
    }
}

class TableSchemaImpl extends TableSchema
{

}
