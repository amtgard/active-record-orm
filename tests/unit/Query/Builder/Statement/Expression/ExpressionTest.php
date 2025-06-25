<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class ExpressionTest extends AmtgardTestCase
{
    private Expression $expression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        // Create a concrete implementation of the abstract Expression class for testing
        $this->expression = new class($this->mockTableSchema, $this->mockFieldSet) extends Expression {
            public function __construct(TableSchema $schema, FieldSet $fieldSet)
            {
                $this->schema = $schema;
                $this->fieldSet = $fieldSet;
            }
            
            public function willEmit(): bool
            {
                return true;
            }
            
            public function emit(): string
            {
                return 'TEST';
            }
            
            public function preparedParameters(): array
            {
                return [];
            }
        };
    }

    public function testGetTableSchema_returnsTableSchema(): void
    {
        $result = $this->expression->getTableSchema();
        
        self::assertSame($this->mockTableSchema, $result);
    }

    public function testGetFieldSet_returnsFieldSet(): void
    {
        $result = $this->expression->getFieldSet();
        
        self::assertSame($this->mockFieldSet, $result);
    }
} 