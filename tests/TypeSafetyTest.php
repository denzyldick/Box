<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;

class TypeSafetyTest extends TestCase
{
    public function setUp(): void
    {
        Hold::reset();
    }

    public function test_error_can_hold_non_exception()
    {
        // Issue 1: Semantics are not enforced.
        // You can hold a "Happy User" inside an Error result.
        Result::Error->hold((object)['status' => 'I am actually happy']);
        
        $val = Result::Error->collect(); // Works, but confusing logic
        
        $this->assertEquals('I am actually happy', $val->status);
    }

    public function test_ok_can_hold_exception()
    {
        // Issue 2: You can accidentally hold an Exception in OK
        Result::Ok->hold(new \Exception("Bad things"));
        
        $val = Result::Ok->collect();
        
        $this->assertInstanceOf(\Exception::class, $val);
    }
    
    public function test_collect_throws_type_error_on_array()
    {
        // Issue 3: Return Type Lie
        // logic says returns `object`, but if I push array...
        Result::Ok->hold(['id' => 1]);
        
        // This fails with TypeError because collect(): object
        try {
            Result::Ok->collect();
            $this->fail("Should strictly fail type check");
        } catch (\TypeError $e) {
            $this->assertTrue(true);
        }
    }
}
