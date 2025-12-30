<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;

class HoldTest extends TestCase
{
    public function setUp(): void
    {
        // Reset the static map before each test to ensure a clean state
        Hold::reset();
    }

    public function test_it_behaves_like_a_stack()
    {
        $obj1 = (object) ['id' => 1];
        $obj2 = (object) ['id' => 2];

        // Push two items
        Result::Ok->hold($obj1);
        Result::Ok->hold($obj2);

        // Verify we have 2 items
        $this->assertSame(2, Hold::count());

        // First pop should be the last item pushed (LIFO)
        $popped1 = Hold::pop();
        $this->assertSame($obj2, $popped1);

        // Second pop should be the first item
        $popped2 = Hold::pop();
        $this->assertSame($obj1, $popped2);
        
        // Stack should be empty now
        $this->assertSame(0, Hold::count());
    }

    public function test_nesting_results_works()
    {
        // Simulate a nested call where an inner result is processed before the outer one completes
        
        // Outer holding
        $outer = (object) ['name' => 'outer'];
        Result::Ok->hold($outer);
        
        // Inner holding
        $inner = (object) ['name' => 'inner'];
        Result::Ok->hold($inner);
        
        // Retrieve inner immediately
        $retrievedInner = Result::Ok->collect();
        $this->assertSame($inner, $retrievedInner);
        
        // Retrieve outer later
        $retrievedOuter = Result::Ok->collect();
        $this->assertSame($outer, $retrievedOuter);
    }
}
