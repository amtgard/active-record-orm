<?php

declare(strict_types=1);

namespace Phake\String;

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

use PHPUnit\Framework\TestCase;

/**
 * Tests the facade class for Phake
 *
 * @author Mike Lively <m@digitalsandwich.com>
 */
class ConverterTest extends TestCase
{
    /**
     * @var Phake\String\Converter
     */
    private $converter;

    /**
     * Sets up the mock generator
     */
    public function setUp(): void
    {
        $this->converter = new Converter();
    }

    public function testObjectConversion()
    {
        $this->assertEquals('<object:stdClass>', $this->converter->convertToString(new \stdClass()));
    }

    public function testArrayConversion()
    {
        $this->assertEquals('<array>', $this->converter->convertToString([]));
    }

    public function testNullConversion()
    {
        $this->assertEquals('<null>', $this->converter->convertToString(null));
    }

    public function testResourceConversion()
    {
        $dir = opendir('/tmp');
        try {
            $this->assertEquals('<resource>', $this->converter->convertToString($dir));
        } catch (Exception $e) {
            closedir($dir);
            throw $e;
        }

        closedir($dir);
    }

    public function testBoolConversion()
    {
        $this->assertEquals('<boolean:true>', $this->converter->convertToString(true));
        $this->assertEquals('<boolean:false>', $this->converter->convertToString(false));
    }

    public function testStringConversion()
    {
        $this->assertEquals('<string:foo>', $this->converter->convertToString('foo'));
    }

    public function testIntConversion()
    {
        $this->assertEquals('<integer:42>', $this->converter->convertToString(42));
    }

    public function testFloatConversion()
    {
        $this->assertEquals('<double:42.01>', $this->converter->convertToString(42.01));
    }
}
