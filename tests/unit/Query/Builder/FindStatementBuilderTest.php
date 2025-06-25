<?php

namespace Tests\Unit\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\Builder\FindStatementBuilder;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\SelectStatement;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class FindStatementBuilderTest extends AmtgardTestCase
{
    public function test()
    {
        $tableSchema = Phake::mock(TableSchema::class);
        $fieldSet = Phake::mock(FieldSet::class);
        
        $findStmtBuilder = FindStatementBuilder::builder()
            ->alias('t')
            ->tableSchema($tableSchema)
            ->fieldSet($fieldSet)
            ->isCount(true)
            ->countAlias('count_alias_optional')
            ->withLimit(true)
            ->offset(10)
            ->rowCount(20)
            ->orderByOperations(['id' => OrderBy::ASC])
            ->fieldSelectors(['id', 'name'])
            ->build();
        
        $select = $findStmtBuilder->getStatement();

        self::assertInstanceOf(SelectStatement::class, $select);

        self::assertEquals('t', $select->getAlias());

        self::assertNotNull($select->getSelectExpression());
        self::assertNotNull($select->getFromExpression());
        self::assertNotNull($select->getWhereExpression());
        self::assertNotNull($select->getOrderBy());
        self::assertNotNull($select->getLimit());

        self::assertEquals(true, $select->getSelectExpression()->getIsCount());
        self::assertEquals('count_alias_optional', $select->getSelectExpression()->getCountAlias());

        self::assertEquals(true, $select->getLimit()->getWithLimit());
        self::assertEquals(10, $select->getLimit()->getOffset());
        self::assertEquals(20, $select->getLimit()->getRowCount());

        self::assertEquals(['id' => OrderBy::ASC], $select->getOrderBy()->getOrderByOperations());
        self::assertEquals('id', $select->getSelectExpression()->getFieldSelectors()[0]);
    }
} 