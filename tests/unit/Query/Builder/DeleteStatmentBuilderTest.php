<?php

namespace Tests\Unit\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\Builder\DeleteStatementBuilder;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\DeleteStatement;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class DeleteStatmentBuilderTest extends AmtgardTestCase
{
    public function test_withoutPrimaryKey()
    {
        $tableSchema = Phake::mock(TableSchema::class);
        $fieldSet = Phake::mock(FieldSet::class);
        $fieldDefinition = Phake::mock(FieldDefinition::class);
        Phake::when($tableSchema)->getPrimaryKey()->thenReturn($fieldDefinition);
        Phake::when($fieldDefinition)->getName()->thenReturn('fieldName');
        $deleteStmtBuilder = DeleteStatementBuilder::builder()->tableSchema($tableSchema)->fieldSet($fieldSet)->build();
        $delete = $deleteStmtBuilder->getStatement();

        self::assertInstanceOf(DeleteStatement::class, $delete);

        Phake::verify($fieldSet, Phake::times(0))->subSet(self::anything());
        Phake::verify($tableSchema)->getPrimaryKey();
        Phake::verify($fieldDefinition)->getName();
        self::assertNotNull($delete->getDeleteExpression());
        self::assertNotNull($delete->getWhereExpression());
    }

    public function test_withPrimaryKey()
    {
        $tableSchema = Phake::mock(TableSchema::class);
        $fieldSet = Phake::mock(FieldSet::class);
        $fieldDefinition = Phake::mock(FieldDefinition::class);
        Phake::when($tableSchema)->getPrimaryKey()->thenReturn($fieldDefinition);
        Phake::when($fieldDefinition)->getName()->thenReturn('fieldName');
        Phake::when($fieldSet)->hasField('fieldName')->thenReturn(true);
        $deleteStmtBuilder = DeleteStatementBuilder::builder()->tableSchema($tableSchema)->fieldSet($fieldSet)->build();
        $delete = $deleteStmtBuilder->getStatement();

        self::assertInstanceOf(DeleteStatement::class, $delete);

        Phake::verify($fieldSet, Phake::times(1))->subSet(self::anything());
        Phake::verify($tableSchema, Phake::times(2))->getPrimaryKey();
        Phake::verify($fieldDefinition, Phake::times(2))->getName();
        self::assertNotNull($delete->getDeleteExpression());
        self::assertNotNull($delete->getWhereExpression());
    }
}