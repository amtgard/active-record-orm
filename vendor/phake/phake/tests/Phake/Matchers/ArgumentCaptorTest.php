<?php

declare(strict_types=1);

namespace Phake\Matchers;

/*
 * Phake - Mocking Framework
 *
 * Copyright (c) 2010-2022, Mike Lively <m@digitalsandwich.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *  *  Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *  *  Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *  *  Neither the name of Mike Lively nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Testing
 * @package    Phake
 * @author     Mike Lively <m@digitalsandwich.com>
 * @copyright  2010 Mike Lively <m@digitalsandwich.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.digitalsandwich.com/
 */

use Phake;
use PHPUnit\Framework\TestCase;

/**
 * Tests the argument captor functionality.
 */
class ArgumentCaptorTest extends TestCase
{
    /**
     * @var ArgumentCaptor
     */
    private $captor;

    /**
     * @var string
     */
    private $refVariable;

    /**
     * Sets up the test fixture
     */
    public function setUp(): void
    {
        $this->captor = new ArgumentCaptor($this->refVariable);
    }

    /**
     * Tests that arguments are captured when matches() is called
     */
    public function testArgumentCapturing()
    {
        $value = ['blah'];
        $this->captor->doArgumentsMatch($value);

        $this->assertEquals('blah', $this->refVariable);
    }

    /**
     * Tests that when a matcher is set on captor it will run the matcher first
     */
    public function testConditionalCapturing()
    {
        $matcher = Phake::mock(IChainableArgumentMatcher::class);
        Phake::when($matcher)->doArgumentsMatch->thenReturn(true);

        $this->captor->when($matcher);

        $value = ['blah'];
        $this->captor->doArgumentsMatch($value);

        Phake::verify($matcher)->doArgumentsMatch(['blah']);

        $this->assertEquals('blah', $this->refVariable);
    }

    /**
     * Tests that when a matcher is set on captor it will run the matcher first
     */
    public function testConditionalCapturingWontCapture()
    {
        $matcher = Phake::mock(IChainableArgumentMatcher::class);
        Phake::when($matcher)->doArgumentsMatch->thenThrow(new Phake\Exception\MethodMatcherException());

        $this->captor->when($matcher);

        $value = ['blah'];
        try {
            $this->captor->doArgumentsMatch($value);
        }
        //Need to atually catch the exception to validate that the refrence didn't change
        catch (Phake\Exception\MethodMatcherException $e) {
            $this->assertNull($this->refVariable);
        }
    }

    public function testConditionalCaptureFailureUpdatesMessage()
    {
        $matcher = Phake::mock(IChainableArgumentMatcher::class);
        Phake::when($matcher)->doArgumentsMatch->thenThrow(new Phake\Exception\MethodMatcherException('test'));

        $this->captor->when($matcher);

        $value = ['blah'];
        try {
            $this->captor->doArgumentsMatch($value);
        }
        //Need to atually catch the exception to validate that the refrence didn't change
        catch (Phake\Exception\MethodMatcherException $e) {
            $this->assertStringStartsWith("Failed in Phake::capture()->when()\n", $e->getMessage(), 'The methodmatcherexception is not prepended with capture info');
        }
    }

    /**
     * Tests that when returns an instance of the captor
     */
    public function testWhenReturn()
    {
        $this->assertSame($this->captor, $this->captor->when(null));
    }

    public function testToString()
    {
        $this->assertEquals('<captured parameter>', $this->captor->__toString());
    }

    public function testToStringWithConditional()
    {
        $matcher = Phake::mock(Phake\Matchers\IChainableArgumentMatcher::class);
        Phake::when($matcher)->__toString()->thenReturn('an argument');
        $this->captor->when($matcher);
        $this->assertEquals('<captured parameter that is an argument>', $this->captor->__toString());
    }

    public function testBindAllCapturedValuePreMatch()
    {
        $value1 = [new \stdClass()];
        $value2 = [new \stdClass()];
        $value3 = [new \stdClass()];

        $this->captor->bindAllCapturedValues($allCaptures);

        $this->captor->doArgumentsMatch($value1);
        $this->captor->doArgumentsMatch($value2);
        $this->captor->doArgumentsMatch($value3);

        $this->assertSame($this->refVariable, $value3[0]);

        $this->assertSame([$value1[0], $value2[0], $value3[0]], $allCaptures);
    }

    public function testBindAllCapturedValuePostMatch()
    {
        $value1 = [new \stdClass()];
        $value2 = [new \stdClass()];
        $value3 = [new \stdClass()];

        $this->captor->doArgumentsMatch($value1);
        $this->captor->doArgumentsMatch($value2);
        $this->captor->doArgumentsMatch($value3);

        $this->captor->bindAllCapturedValues($allCaptures);

        $this->assertSame($this->refVariable, $value3[0]);

        $this->assertSame([$value1[0], $value2[0], $value3[0]], $allCaptures);
    }
}
