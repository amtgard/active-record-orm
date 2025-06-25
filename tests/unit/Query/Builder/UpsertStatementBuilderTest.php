<?php

namespace Tests\Unit\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\Builder\UpsertStatementBuilder;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\UpdateStatement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\InsertStatement;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\assertTrue;

class UpsertStatementBuilderTest extends AmtgardTestCase
{
    public function testUpdatePath()
    {
        $tableSchema = Phake::mock(TableSchema::class);
        $fieldSet = Phake::mock(FieldSet::class);
        $fieldDefinition = Phake::mock(FieldDefinition::class);
        $subFieldSet = Phake::mock(FieldSet::class);
        
        Phake::when($tableSchema)->getPrimaryKey()->thenReturn($fieldDefinition);
        Phake::when($fieldDefinition)->getName()->thenReturn('id');
        Phake::when($tableSchema)->primaryKeyIsSet($fieldSet)->thenReturn(true);
        Phake::when($fieldSet)->subSet(['id'])->thenReturn($subFieldSet);
        
        $upsertStmtBuilder = UpsertStatementBuilder::builder()
            ->alias('t')
            ->tableSchema($tableSchema)
            ->fieldSet($fieldSet)
            ->build();
        
        $update = $upsertStmtBuilder->getStatement();

        self::assertInstanceOf(UpdateStatement::class, $update);

        self::assertNotNull($update->getUpdateExpression());
        self::assertNotNull($update->getWhereExpression());

        Phake::verify($tableSchema)->primaryKeyIsSet($fieldSet);
        Phake::verify($tableSchema)->getPrimaryKey();
        Phake::verify($fieldDefinition)->getName();
        Phake::verify($fieldSet)->subSet(['id']);
    }

    public function testInsertPath()
    {
        $tableSchema = Phake::mock(TableSchema::class);
        $fieldSet = Phake::mock(FieldSet::class);
        $postQueryCallback = fn() => assertTrue(true);

        Phake::when($tableSchema)->primaryKeyIsSet($fieldSet)->thenReturn(false);
        
        $upsertStmtBuilder = UpsertStatementBuilder::builder()
            ->alias('t')
            ->tableSchema($tableSchema)
            ->fieldSet($fieldSet)
            ->postQueryCallback($postQueryCallback)
            ->build();
        
        $insert = $upsertStmtBuilder->getStatement();

        self::assertInstanceOf(InsertStatement::class, $insert);

        self::assertNotNull($insert->getInsertExpression());
        self::assertNotNull($upsertStmtBuilder->getPostQueryCallback());

        Phake::verify($tableSchema)->primaryKeyIsSet($fieldSet);
    }
} 