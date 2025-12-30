<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;
use Fiber;

class ConcurrencyTest extends TestCase
{
    public function setUp(): void
    {
        // Reset state
        Hold::reset();
    }

    public function test_fibers_have_isolated_stacks()
    {
        if (!class_exists(Fiber::class)) {
            $this->markTestSkipped('Fibers not available');
        }

        // Fiber 1 pushes 'A'
        $fiber1 = new Fiber(function () {
            Result::Ok->hold((object)['id' => 'A']);
            Fiber::suspend();
            
            // Should verify that we only see 'A' here, not 'B'
            $popped = Hold::pop();
            return $popped->id;
        });

        // Fiber 2 pushes 'B'
        $fiber2 = new Fiber(function () {
            Result::Ok->hold((object)['id' => 'B']);
            Fiber::suspend();
            
            $popped = Hold::pop();
            return $popped->id;
        });

        // Start Fiber 1 (Pushes A)
        $fiber1->start();
        
        // Start Fiber 2 (Pushes B) - This would overwrite A in a global unsafe stack
        $fiber2->start();

        // Resume Fiber 1 - Should pop A
        $fiber1->resume();
        $id1 = $fiber1->getReturn();
        
        // Resume Fiber 2 - Should pop B
        $fiber2->resume();
        $id2 = $fiber2->getReturn();

        $this->assertEquals('A', $id1, 'Fiber 1 should get A');
        $this->assertEquals('B', $id2, 'Fiber 2 should get B');
    }
}
