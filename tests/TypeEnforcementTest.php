<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;

class TypeEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        Hold::reset();
    }

    public function test_ok_cannot_hold_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Result::Ok cannot hold an Exception");

        Result::Ok->hold(new \Exception("Oops"));
    }

    public function test_error_must_hold_throwable()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Result::Error must hold a Throwable");

        Result::Error->hold((object)['message' => 'Not an exception']);
    }

    public function test_collect_returns_array_correctly()
    {
        Result::Ok->hold(['foo' => 'bar']);
        
        $val = Result::Ok->collect();
        
        $this->assertIsArray($val);
        $this->assertEquals('bar', $val['foo']);
    }
}
