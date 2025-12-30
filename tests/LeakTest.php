<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;

class LeakTest extends TestCase
{
    public function setUp(): void
    {
        Hold::reset();
    }

    public function test_forgotten_pop_corrupts_next_call()
    {
        // 1. User call A returns Ok('A'), but user ignores the value (e.g. strict boolean check)
        Result::Ok->hold((object)['id' => 'A']);
        
        // Simulating: match(Result::Ok) { case Ok => "I don't need the value"; }
        // The value 'A' is STILL in the stack.

        // 2. User call B returns Ok('B')
        Result::Ok->hold((object)['id' => 'B']);

        // 3. User tries to get B
        $result = Result::Ok->collect();

        // EXPECTATION: We want B
        // REALITY: We get B (because it's LIFO). 
        $this->assertEquals('B', $result->id);

        // 4. BUT, strictly speaking, 'A' is still leaking at the bottom of the stack.
        $this->assertSame(1, Hold::count(), "Stack should be empty, but 'A' is leaking!");
        
        // 5. Dangerous Scenario: User call C tries to pop, creates underflow or gets wrong data?
        // Actually LIFO protects the immediate retrieval, but the memory leaks.
        
        // Let's try the Inverse:
        // What if I pop TWICE by accident? Or if I reuse the Enum?
    }

    public function test_double_usage_corruption()
    {
        // Call 1
        Result::Ok->hold((object)['val' => 1]);
        // User forgets to pop.
        
        // Call 2
        Result::Ok->hold((object)['val' => 2]);
        $val2 = Result::Ok->collect(); // Gets 2. 
        
        // Next time someone calls pop() randomly or if logical flow is weird:
        $leaked = Result::Ok->collect(); // OOPS! We got 1 from the previous dead request!
        $this->assertEquals(1, $leaked->val);
    }
}
