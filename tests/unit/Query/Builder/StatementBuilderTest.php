<?php

namespace Tests\Unit\Query\Builder;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\ActiveRecordOrm\Query\Builder\StatementBuilder;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class StatementBuilderTest extends AmtgardTestCase
{
    private StatementBuilder $statementBuilder;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        // Create a concrete implementation of the abstract StatementBuilder class for testing
        $this->statementBuilder = new class extends StatementBuilder {
            public function __construct()
            {
                // Constructor for the anonymous class
            }
            
            public function setTableSchema(TableSchema $tableSchema): void
            {
                $this->tableSchema = $tableSchema;
            }
            
            public function setFieldSet(FieldSet $fieldSet): void
            {
                $this->fieldSet = $fieldSet;
            }
        };
        
        $this->statementBuilder->setTableSchema($this->mockTableSchema);
        $this->statementBuilder->setFieldSet($this->mockFieldSet);
    }

    public function testGetStatement_throwsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        
        $this->statementBuilder->getStatement();
    }
} 