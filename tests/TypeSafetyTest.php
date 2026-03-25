<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;

class TypeSafetyTest extends TestCase
{
    public function test_error_cannot_hold_non_exception()
    {
        $this->expectException(\TypeError::class);
        
        // Error factory strictly requires a Throwable
        Result::error((object)['status' => 'I am actually happy']);
    }

    public function test_ok_cannot_hold_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Result::ok cannot hold a Throwable. Use Result::error instead.");
        
        Result::ok(new \Exception("Bad things"));
    }
    
    public function test_collect_supports_array()
    {
        $res = Result::ok(['id' => 1]);
        
        $val = $res->collect();
        $this->assertEquals(1, $val['id']);
    }

    public function test_cannot_collect_from_error()
    {
        $res = Result::error(new \Exception("fail"));
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot collect value from an Error result.");
        
        $res->collect();
    }
}
