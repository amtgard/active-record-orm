<?php

declare(strict_types=1);

namespace Phake\ClassGenerator\InvocationHandler;

/*
 * Phake - Mocking Framework
 *
 * Copyright (c) 2010-2022, Mike Lively <mike.lively@sellingsource.com>
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

class StubCallerTest extends TestCase
{
    /**
     * @var StubCaller
     */
    private $handler;

    /**
     * @var Phake\IMock
     */
    private $mock;

    /**
     * @var Phake\Stubber\AnswerCollection
     */
    private $answerCollection;

    /**
     * @var Phake\Stubber\StubMapper
     */
    private $stubMapper;

    /**
     * @var Phake\Stubber\IAnswer
     */
    private $defaultAnswer;

    public function setUp(): void
    {
        Phake::initAnnotations($this);
        $this->mock          = $this->getMockBuilder(Phake\IMock::class)->getMock();
        $this->stubMapper    = Phake::mock(Phake\Stubber\StubMapper::class);
        $this->defaultAnswer = Phake::mock(Phake\Stubber\IAnswer::class);
        Phake::when($this->defaultAnswer)->getAnswerCallback('foo')->thenReturn(function () {
            return '24';
        });

        $this->answerCollection = Phake::mock(Phake\Stubber\AnswerCollection::class);
        $answer                 = Phake::mock(Phake\Stubber\IAnswer::class);
        Phake::when($this->answerCollection)->getAnswer()->thenReturn($answer);
        Phake::when($answer)->getAnswerCallback($this->anything(), 'foo')->thenReturn(function () {
            return '42';
        });
        Phake::when($this->stubMapper)->getStubByCall(Phake::anyParameters())->thenReturn($this->answerCollection);

        $this->handler = new StubCaller($this->stubMapper, $this->defaultAnswer);
    }

    public function testImplementIInvocationHandler()
    {
        $this->assertInstanceOf(IInvocationHandler::class, $this->handler);
    }

    public function testStubIsPulled()
    {
        $ref = ['bar'];
        $this->handler->invoke($this->mock, 'foo', $ref, $ref);

        Phake::verify($this->stubMapper)->getStubByCall('foo', ['bar']);
    }

    public function testAnswerReturned()
    {
        $ref = ['bar'];

        $this->assertEquals('42', call_user_func($this->handler->invoke($this->mock, 'foo', $ref, $ref)->getAnswerCallback($this->mock, 'foo'), 'bar'));
    }

    public function testMagicCallMethodChecksForImplicitStubFirst()
    {
        $ref = ['foo', ['bar']];
        Phake::when($this->stubMapper)->getStubByCall(Phake::anyParameters())->thenReturn(null);

        $this->handler->invoke($this->mock, '__call', $ref, $ref);

        Phake::inOrder(
            Phake::verify($this->stubMapper)->getStubByCall('foo', ['bar']),
            Phake::verify($this->stubMapper)->getStubByCall('__call', ['foo', ['bar']])
        );
    }

    public function testMagicStaticCallMethodChecksForImplicitStubFirst()
    {
        $ref = ['foo', ['bar']];
        Phake::when($this->stubMapper)->getStubByCall(Phake::anyParameters())->thenReturn(null);

        $this->handler->invoke($this->mock, '__callStatic', $ref, $ref);

        Phake::inOrder(
            Phake::verify($this->stubMapper)->getStubByCall('foo', ['bar']),
            Phake::verify($this->stubMapper)->getStubByCall('__callStatic', ['foo', ['bar']])
        );
    }

    public function testMagicCallMethodBypassesExplicitStub()
    {
        $ref = ['foo', ['bar']];

        $this->handler->invoke($this->mock, '__call', $ref, $ref);

        Phake::verify($this->stubMapper, Phake::times(0))->getStubByCall('__call', ['foo', ['bar']]);
    }
}
