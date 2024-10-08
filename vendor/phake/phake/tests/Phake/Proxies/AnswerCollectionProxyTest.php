<?php

declare(strict_types=1);

namespace Phake\Proxies;

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

class AnswerCollectionProxyTest extends TestCase
{
    /**
     * @var Phake\Proxies\AnswerCollectionProxyTest
     */
    private $proxy;

    /**
     * @var Phake\Stubber\AnswerCollection
     */
    private $container;

    /**
     * Sets up the test fixture
     */
    public function setUp(): void
    {
        $this->container = Phake::mock(Phake\Stubber\AnswerCollection::class);
        $this->proxy     = new AnswerCollectionProxy($this->container);
    }

    /**
     * Tests the thenReturn functionality of the proxy.
     *
     * It should result in the binder being called with a static answer.
     *
     * @todo we need argument capturing so I can make sure the answer matches.
     */
    public function testThenReturn()
    {
        $this->assertSame($this->proxy, $this->proxy->thenReturn(42));

        Phake::verify($this->container)->addAnswer(Phake::capture($answer));

        $this->assertInstanceOf(\Phake\Stubber\Answers\StaticAnswer::class, $answer);
        $this->phakeAssertAttributeEqualTo(42, 'answer', $answer);
    }

    /**
     * Tests the thenGetReturnByLambda functionality of the proxy
     *
     * It should result in the binder being called with a lambda answer
     */
    public function testThenGetReturnByLambda()
    {
        $func = function ($arg1) {
            return $arg1;
        };

        $this->assertSame($this->proxy, $this->proxy->thenGetReturnByLambda($func));

        Phake::verify($this->container)->addAnswer(Phake::capture($answer));

        $this->assertInstanceOf(\Phake\Stubber\Answers\LambdaAnswer::class, $answer);
        $this->phakeAssertAttributeEqualTo($func, 'answerLambda', $answer);
    }

    /**
     * Tests that thenGetReturnByLambda throws an exception if the given lambda is not callable
     */
    public function testThenGetReturnByLambdaThrowsExceptionForUncallableLambda()
    {
        $this->expectException('InvalidArgumentException');

        $func = 'some_unknown_function';
        $this->proxy->thenGetReturnByLambda($func);
    }

    /**
     * Tests the thenCallParent functionality of the proxy
     */
    public function testThenCallParent()
    {
        $this->assertSame($this->proxy, $this->proxy->thenCallParent());

        Phake::verify($this->container)->addAnswer($this->isInstanceOf(\Phake\Stubber\Answers\ParentDelegate::class));
    }

    /**
     * Tests that captureReturnTo does it's thing
     */
    public function testCaptureReturnTo()
    {
        $var = null;
        $this->assertSame($this->proxy, $this->proxy->captureReturnTo($var));

        Phake::verify($this->container)->addAnswer($this->isInstanceOf(\Phake\Stubber\Answers\ParentDelegate::class));
    }

    /**
     * Tests the thenThrow functionality of the proxy.
     */
    public function testThenThrow()
    {
        $exception = new \RuntimeException();

        $this->assertSame($this->proxy, $this->proxy->thenThrow($exception));

        Phake::verify($this->container)->addAnswer(Phake::capture($answer));

        $this->assertInstanceOf(\Phake\Stubber\Answers\ExceptionAnswer::class, $answer);
        $this->phakeAssertAttributeEqualTo($exception, 'answer', $answer);
    }

    private function phakeAssertAttributeEqualTo($expectedValue, string $propertyName, $object)
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionProperty = $reflectionObject->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);
        $value = $reflectionProperty->getValue($object);

        $this->assertEquals($expectedValue, $value);
    }
}
