<?php

namespace Tests\Unit\Query\Builder\Statement;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class StatementTest extends AmtgardTestCase
{
    private Statement $statement;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a concrete implementation of the abstract Statement class for testing
        $this->statement = new class extends Statement {
            private array $expressions = [];
            
            public function setExpressions(array $expressions): void
            {
                $this->expressions = $expressions;
            }
            
            protected function orderedExpressionMap(): array
            {
                return $this->expressions;
            }
        };
    }

    public function testBuildSql_withExpressionsThatWillEmit_returnsConcatenatedSql(): void
    {
        // Create mock expressions
        $expression1 = Phake::mock(Expression::class);
        $expression2 = Phake::mock(Expression::class);
        $expression3 = Phake::mock(Expression::class);
        
        // Set up mocks to return willEmit() = true and emit() values
        Phake::when($expression1)->willEmit()->thenReturn(true);
        Phake::when($expression1)->emit()->thenReturn('SELECT *');
        
        Phake::when($expression2)->willEmit()->thenReturn(true);
        Phake::when($expression2)->emit()->thenReturn('FROM users');
        
        Phake::when($expression3)->willEmit()->thenReturn(true);
        Phake::when($expression3)->emit()->thenReturn('WHERE id = 1');
        
        // Set expressions in the test statement
        $this->statement->setExpressions([$expression1, $expression2, $expression3]);
        
        $result = $this->statement->buildSql();
        
        self::assertEquals('SELECT * FROM users WHERE id = 1', $result);
        
        // Verify all expressions were called
        Phake::verify($expression1)->willEmit();
        Phake::verify($expression1)->emit();
        Phake::verify($expression2)->willEmit();
        Phake::verify($expression2)->emit();
        Phake::verify($expression3)->willEmit();
        Phake::verify($expression3)->emit();
    }

    public function testBuildSql_withExpressionsThatWillNotEmit_returnsSqlWithoutThoseExpressions(): void
    {
        // Create mock expressions
        $expression1 = Phake::mock(Expression::class);
        $expression2 = Phake::mock(Expression::class);
        $expression3 = Phake::mock(Expression::class);
        
        // Set up mocks - expression2 will not emit
        Phake::when($expression1)->willEmit()->thenReturn(true);
        Phake::when($expression1)->emit()->thenReturn('SELECT *');
        
        Phake::when($expression2)->willEmit()->thenReturn(false);
        // expression2->emit() should not be called
        
        Phake::when($expression3)->willEmit()->thenReturn(true);
        Phake::when($expression3)->emit()->thenReturn('WHERE id = 1');
        
        // Set expressions in the test statement
        $this->statement->setExpressions([$expression1, $expression2, $expression3]);
        
        $result = $this->statement->buildSql();
        
        self::assertEquals('SELECT * WHERE id = 1', preg_replace('/\s+/', ' ', $result));
        
        // Verify all willEmit() were called, but only emitting expressions had emit() called
        Phake::verify($expression1)->willEmit();
        Phake::verify($expression1)->emit();
        Phake::verify($expression2)->willEmit();
        Phake::verify($expression2, Phake::never())->emit();
        Phake::verify($expression3)->willEmit();
        Phake::verify($expression3)->emit();
    }

    public function testBuildSql_withNoExpressions_returnsEmptyString(): void
    {
        $this->statement->setExpressions([]);
        
        $result = $this->statement->buildSql();
        
        self::assertEquals('', $result);
    }

    public function testGetStatementParams_withExpressionsThatWillEmit_returnsMergedParameters(): void
    {
        // Create mock expressions
        $expression1 = Phake::mock(Expression::class);
        $expression2 = Phake::mock(Expression::class);
        $expression3 = Phake::mock(Expression::class);
        
        // Set up mocks to return willEmit() = true and preparedParameters() values
        Phake::when($expression1)->willEmit()->thenReturn(true);
        Phake::when($expression1)->preparedParameters()->thenReturn(['name' => 'John']);
        
        Phake::when($expression2)->willEmit()->thenReturn(true);
        Phake::when($expression2)->preparedParameters()->thenReturn(['email' => 'john@example.com']);
        
        Phake::when($expression3)->willEmit()->thenReturn(true);
        Phake::when($expression3)->preparedParameters()->thenReturn(['age' => 30]);
        
        // Set expressions in the test statement
        $this->statement->setExpressions([$expression1, $expression2, $expression3]);
        
        $result = $this->statement->getStatementParams();
        
        $expectedParams = [
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 30
        ];
        self::assertEquals($expectedParams, $result);
        
        // Verify all expressions were called
        Phake::verify($expression1)->willEmit();
        Phake::verify($expression1)->preparedParameters();
        Phake::verify($expression2)->willEmit();
        Phake::verify($expression2)->preparedParameters();
        Phake::verify($expression3)->willEmit();
        Phake::verify($expression3)->preparedParameters();
    }

    public function testGetStatementParams_withExpressionsThatWillNotEmit_returnsParametersOnlyFromEmittingExpressions(): void
    {
        // Create mock expressions
        $expression1 = Phake::mock(Expression::class);
        $expression2 = Phake::mock(Expression::class);
        $expression3 = Phake::mock(Expression::class);
        
        // Set up mocks - expression2 will not emit
        Phake::when($expression1)->willEmit()->thenReturn(true);
        Phake::when($expression1)->preparedParameters()->thenReturn(['name' => 'John']);
        
        Phake::when($expression2)->willEmit()->thenReturn(false);
        // expression2->preparedParameters() should not be called
        
        Phake::when($expression3)->willEmit()->thenReturn(true);
        Phake::when($expression3)->preparedParameters()->thenReturn(['age' => 30]);
        
        // Set expressions in the test statement
        $this->statement->setExpressions([$expression1, $expression2, $expression3]);
        
        $result = $this->statement->getStatementParams();
        
        $expectedParams = [
            'name' => 'John',
            'age' => 30
        ];
        self::assertEquals($expectedParams, $result);
        
        // Verify all willEmit() were called, but only emitting expressions had preparedParameters() called
        Phake::verify($expression1)->willEmit();
        Phake::verify($expression1)->preparedParameters();
        Phake::verify($expression2)->willEmit();
        Phake::verify($expression2, Phake::never())->preparedParameters();
        Phake::verify($expression3)->willEmit();
        Phake::verify($expression3)->preparedParameters();
    }

    public function testGetStatementParams_withNoExpressions_returnsEmptyArray(): void
    {
        $this->statement->setExpressions([]);
        
        $result = $this->statement->getStatementParams();
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testGetStatementParams_withExpressionsHavingEmptyParameters_returnsMergedParameters(): void
    {
        // Create mock expressions
        $expression1 = Phake::mock(Expression::class);
        $expression2 = Phake::mock(Expression::class);
        
        // Set up mocks - one with parameters, one with empty parameters
        Phake::when($expression1)->willEmit()->thenReturn(true);
        Phake::when($expression1)->preparedParameters()->thenReturn(['name' => 'John']);
        
        Phake::when($expression2)->willEmit()->thenReturn(true);
        Phake::when($expression2)->preparedParameters()->thenReturn([]);
        
        // Set expressions in the test statement
        $this->statement->setExpressions([$expression1, $expression2]);
        
        $result = $this->statement->getStatementParams();
        
        $expectedParams = ['name' => 'John'];
        self::assertEquals($expectedParams, $result);
        
        // Verify all expressions were called
        Phake::verify($expression1)->willEmit();
        Phake::verify($expression1)->preparedParameters();
        Phake::verify($expression2)->willEmit();
        Phake::verify($expression2)->preparedParameters();
    }
} 