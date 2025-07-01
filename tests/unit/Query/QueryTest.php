<?php

namespace Tests\Unit\Query;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Query;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class QueryTest extends AmtgardTestCase
{
    private Query $query;
    private DataAccessPolicy $mockPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockPolicy = Phake::mock(DataAccessPolicy::class);
        
        $this->query = Query::builder()
            ->sql('SELECT * FROM users WHERE id = :id')
            ->params(['id' => 123])
            ->policy($this->mockPolicy)
            ->postQueryCallback(fn() => true)
            ->build();
    }

    public function testHash_returnsMd5HashOfJsonRepresentation(): void
    {
        $result = $this->query->hash();
        
        self::assertIsString($result);
        self::assertEquals(32, strlen($result));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result);
    }

    public function testHash_withDifferentQueries_returnsDifferentHashes(): void
    {
        $query1 = Query::builder()
            ->sql('SELECT * FROM users')
            ->params([])
            ->policy($this->mockPolicy)
            ->build();
            
        $query2 = Query::builder()
            ->sql('SELECT * FROM orders')
            ->params([])
            ->policy($this->mockPolicy)
            ->build();
        
        $hash1 = $query1->hash();
        $hash2 = $query2->hash();
        
        self::assertNotEquals($hash1, $hash2);
    }

    public function testApply_clearsDatabaseAndSetsParamsThenExecutes(): void
    {
        $mockDatabase = Phake::mock(Database::class);
        $mockRecordSet = Phake::mock(RecordSet::class);
        
        Phake::when($mockDatabase)->execute('SELECT * FROM users WHERE id = :id')->thenReturn($mockRecordSet);
        
        $result = $this->query->apply($mockDatabase);
        
        self::assertSame($mockRecordSet, $result);
        Phake::verify($mockDatabase)->clear();
        Phake::verify($mockDatabase)->__set('id', 123);
        Phake::verify($mockDatabase)->execute('SELECT * FROM users WHERE id = :id');
    }

    public function testApply_withMultipleParams_setsAllParams(): void
    {
        $query = Query::builder()
            ->sql('SELECT * FROM users WHERE id = :id AND name = :name')
            ->params(['id' => 123, 'name' => 'John'])
            ->policy($this->mockPolicy)
            ->build();
            
        $mockDatabase = Phake::mock(Database::class);
        $mockRecordSet = Phake::mock(RecordSet::class);
        
        Phake::when($mockDatabase)->execute(Phake::anyParameters())->thenReturn($mockRecordSet);
        
        $query->apply($mockDatabase);
        
        Phake::verify($mockDatabase)->clear();
        Phake::verify($mockDatabase)->__set('id', 123);
        Phake::verify($mockDatabase)->__set('name', 'John');
        Phake::verify($mockDatabase)->execute(Phake::anyParameters());
    }

    public function testPostQuery_withCallableCallback_executesCallback(): void
    {
        $callbackExecuted = false;
        $callback = function() use (&$callbackExecuted) {
            $callbackExecuted = true;
        };
        
        $query = Query::builder()
            ->sql('SELECT * FROM users')
            ->params([])
            ->policy($this->mockPolicy)
            ->postQueryCallback($callback)
            ->build();
        
        $query->postQuery();
        
        self::assertTrue($callbackExecuted);
    }

    public function testPostQuery_withNonCallableCallback_doesNothing(): void
    {
        $query = Query::builder()
            ->sql('SELECT * FROM users')
            ->params([])
            ->policy($this->mockPolicy)
            ->postQueryCallback('not_callable')
            ->build();
        
        // Should not throw any exception
        $query->postQuery();
        
        self::assertTrue(true); // Test passes if no exception is thrown
    }

    public function testJsonSerialize_returnsAllObjectVars(): void
    {
        $result = $this->query->jsonSerialize();
        
        self::assertIsArray($result);
        self::assertArrayHasKey('sql', $result);
        self::assertArrayHasKey('params', $result);
        self::assertArrayHasKey('policy', $result);
        self::assertArrayHasKey('postQueryCallback', $result);
        self::assertEquals('SELECT * FROM users WHERE id = :id', $result['sql']);
        self::assertEquals(['id' => 123], $result['params']);
        self::assertSame($this->mockPolicy, $result['policy']);
        self::assertIsCallable($result['postQueryCallback']);
    }

    public function testJsonSerialize_withMinimalQuery_returnsMinimalVars(): void
    {
        $query = Query::builder()
            ->sql('SELECT * FROM users')
            ->params([])
            ->policy($this->mockPolicy)
            ->build();
        
        $result = $query->jsonSerialize();
        
        self::assertIsArray($result);
        self::assertArrayHasKey('sql', $result);
        self::assertArrayHasKey('params', $result);
        self::assertArrayHasKey('policy', $result);
        self::assertArrayHasKey('postQueryCallback', $result);
        self::assertEquals('SELECT * FROM users', $result['sql']);
        self::assertEquals([], $result['params']);
        self::assertSame($this->mockPolicy, $result['policy']);
        self::assertNull($result['postQueryCallback']);
    }
} 