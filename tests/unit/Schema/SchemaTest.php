<?php

namespace Tests\Unit\Schema;

use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\Schema;
use Amtgard\ActiveRecordOrm\Utility\Constants;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use function PHPUnit\Framework\assertEquals;

class SchemaTest extends AmtgardTestCase
{
    private FieldDefinition $fuzzy;
    private FieldDefinition $bear;
    private Schema $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fuzzy = Phake::mock(FieldDefinition::class);
        $this->bear = Phake::mock(FieldDefinition::class);
        Phake::when($this->fuzzy)->getName()->thenReturn('fuzzy');
        Phake::when($this->bear)->getName()->thenReturn('bear');
        $this->schema = Schema::builder()->fields(['fuzzy' => $this->fuzzy, 'bear' => $this->bear])->build();
    }

    public function testFindOneField(): void
    {
        self::assertNotNull($this->schema->getField('fuzzy'));
        assertEquals('fuzzy', $this->schema->getField('fuzzy')->getName());
    }

    public function testFetchAllFields() {
        assertEquals(2, count($this->schema->getFields()));
    }

    public function testHasField_withMatch() {
        self::assertTrue($this->schema->hasField('fuzzy'));
    }

    public function testHasField_withNoMatch() {
        self::assertThrows(\InvalidArgumentException::class, function () {
            self::assertFalse($this->schema->hasField('wuzzy'));
        });

        try {
            $this->schema->hasField('wuzzy');
        } catch (\InvalidArgumentException $e) {
            self::assertEquals(sprintf(Constants::$SCHEMA_FIELD_MISS_ERROR, 'wuzzy', 'fuzzy'), $e->getMessage());
        }
    }

    public function testFuzzyFieldSuggestion() {
        assertEquals('fuzzy', $this->schema->suggestField('wuzzy'));
    }
}