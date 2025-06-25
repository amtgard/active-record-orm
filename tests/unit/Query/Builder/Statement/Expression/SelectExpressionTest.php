<?php

namespace Tests\Unit\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\SelectExpression;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class SelectExpressionTest extends AmtgardTestCase
{
    private SelectExpression $selectExpression;
    private TableSchema $mockTableSchema;
    private FieldSet $mockFieldSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockFieldSet = Phake::mock(FieldSet::class);
        
        $this->selectExpression = new SelectExpression();
        
        // Set up the protected properties using reflection
        $reflection = new \ReflectionClass($this->selectExpression);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->selectExpression, $this->mockTableSchema);
        
        $fieldSetProperty = $reflection->getProperty('fieldSet');
        $fieldSetProperty->setAccessible(true);
        $fieldSetProperty->setValue($this->selectExpression, $this->mockFieldSet);
    }

    public function testWillEmit_alwaysReturnsTrue(): void
    {
        $result = $this->selectExpression->willEmit();
        
        self::assertTrue($result);
    }

    public function testEmit_withCountMode_returnsCountQuery(): void
    {
        // Set up reflection to access private properties
        $reflection = new \ReflectionClass($this->selectExpression);
        $isCountProperty = $reflection->getProperty('isCount');
        $isCountProperty->setAccessible(true);
        $isCountProperty->setValue($this->selectExpression, true);
        
        $countAliasProperty = $reflection->getProperty('countAlias');
        $countAliasProperty->setAccessible(true);
        $countAliasProperty->setValue($this->selectExpression, 'custom_count');
        
        $result = $this->selectExpression->emit();
        
        self::assertEquals('SELECT COUNT(*) as custom_count', $result);
    }

    public function testEmit_withFieldSelectors_returnsSelectWithFields(): void
    {
        // Set up reflection to access private properties
        $reflection = new \ReflectionClass($this->selectExpression);
        $isCountProperty = $reflection->getProperty('isCount');
        $isCountProperty->setAccessible(true);
        $isCountProperty->setValue($this->selectExpression, false);
        
        $fieldSelectorsProperty = $reflection->getProperty('fieldSelectors');
        $fieldSelectorsProperty->setAccessible(true);
        $fieldSelectorsProperty->setValue($this->selectExpression, ['field1', 'field2']);
        
        // Mock field definitions
        $field1 = Phake::mock(FieldDefinition::class);
        $field2 = Phake::mock(FieldDefinition::class);
        Phake::when($field1)->getName()->thenReturn('field1');
        Phake::when($field2)->getName()->thenReturn('field2');
        
        Phake::when($this->mockTableSchema)->getFields()->thenReturn([$field1, $field2]);
        
        $result = $this->selectExpression->emit();
        
        self::assertEquals('SELECT field1, field2', $result);
        Phake::verify($this->mockTableSchema)->getFields();
    }

    public function testEmit_withoutFieldSelectors_returnsSelectStar(): void
    {
        // Set up reflection to access private properties
        $reflection = new \ReflectionClass($this->selectExpression);
        $isCountProperty = $reflection->getProperty('isCount');
        $isCountProperty->setAccessible(true);
        $isCountProperty->setValue($this->selectExpression, false);
        
        $fieldSelectorsProperty = $reflection->getProperty('fieldSelectors');
        $fieldSelectorsProperty->setAccessible(true);
        $fieldSelectorsProperty->setValue($this->selectExpression, []);
        
        $result = $this->selectExpression->emit();
        
        self::assertEquals('SELECT *', $result);
    }

    public function testPreparedParameters_returnsEmptyArray(): void
    {
        $result = $this->selectExpression->preparedParameters();
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }
}