<?php

declare(strict_types=1);

namespace Phake\Proxies;

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

/**
 * A proxy to handle stubbing a method on a mock object.
 *
 * @author Mike Lively <m@digitalsandwich.com>
 */
class StubberProxy
{
    use NamedArgumentsResolver;

    /**
     * @var \Phake\IMock|class-string
     */
    private $obj;

    /**
     * @var \Phake\Matchers\Factory
     */
    private $matcherFactory;

    /**
     * @param \Phake\IMock|class-string     $obj
     * @param \Phake\Matchers\Factory $matcherFactory
     */
    public function __construct($obj, \Phake\Matchers\Factory $matcherFactory)
    {
        \Phake::assertValidMock($obj);
        $this->obj            = $obj;
        $this->matcherFactory = $matcherFactory;
    }

    /**
     * A magic call to instantiate an Answer Binder Proxy.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return AnswerBinderProxy
     */
    public function __call($method, array $arguments)
    {
        $matcher = new \Phake\Matchers\MethodMatcher($method, $this->matcherFactory->createMatcherChain($this->resolveNamedArguments($this->obj, $method, $arguments)));
        $binder  = new \Phake\Stubber\AnswerBinder($matcher, \Phake::getInfo($this->obj)->getStubMapper());
        return new AnswerBinderProxy($binder);
    }

    /**
     * A magic call to instantiate an Answer Binder Proxy that matches any parameters.
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     * @psalm-suppress DocblockTypeContradiction
     *
     * @param string $method
     *
     * @throws \InvalidArgumentException if $method is not a valid parameter/method name
     *
     * @return AnswerBinderProxy
     */
    public function __get($method)
    {
        if (is_string($method) && ctype_digit($method[0])) {
            throw new \InvalidArgumentException('String parameter to __get() cannot start with an integer');
        }

        if (!is_string($method) && !is_object($method)) { // assume an object is a matcher
            $message = sprintf('Parameter to __get() must be a string, %s given', gettype($method));
            throw new \InvalidArgumentException($message);
        }

        if (method_exists($this->obj, '__get') && !(is_string($method) && method_exists($this->obj, $method))) {
            return $this->__call('__get', [$method]);
        }

        return $this->__call($method, [new \Phake\Matchers\AnyParameters()]);
    }
}
