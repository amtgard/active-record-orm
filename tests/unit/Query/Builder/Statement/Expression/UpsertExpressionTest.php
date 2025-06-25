<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\UpsertExpression;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class UpsertExpressionTest extends AmtgardTestCase
{
    private UpsertExpression $upsertExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->upsertExpression = new UpsertExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->upsertExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->upsertExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->upsertExpression, $this->mockFieldSet);
    }

    public function testWillEmit_alwaysReturnsFalse(): void
    {
        $result = $this->upsertExpression->willEmit();
        
        self::assertFalse($result);
    }

    public function testEmit_returnsEmptyString(): void
    {
        $result = $this->upsertExpression->emit();
        
        self::assertEquals('', $result);
    }

    public function testPreparedParameters_returnsEmptyArray(): void
    {
        $result = $this->upsertExpression->preparedParameters();
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }
} 