<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;
use Fiber;

class GarbageCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        Hold::reset();
    }

    public function test_fiber_cleanup_removes_held_items()
    {
        if (!class_exists(Fiber::class)) {
            $this->markTestSkipped('Fibers not available');
        }

        $fiber = new Fiber(function () {
            // Push an item in this fiber
            Result::Ok->hold((object)['id' => 'leak?']);
            Fiber::suspend();
        });

        $fiber->start();
        
        // Assert the map has the item (using reflection since keys are objects now)
        // We can't use count() easily from outside because count() uses getCurrent()
        // But we can rely on WeakMap mechanics.
        
        // Let's verify by checking memory or behavior? 
        // Actually, we can check if the WeakMap is empty after the fiber is gone.
        
        $fiber = null; // Destroy the fiber
        
        // Force GC
        gc_collect_cycles();
        
        // The WeakMap should now be empty (or at least not contain that fiber's entry)
        // Since we can't inspect private WeakMap easily without reflection, 
        // we trust the standard library behavior, or reflect:
        
        $reflection = new \ReflectionClass(Hold::class);
        $property = $reflection->getProperty('map');
        $property->setAccessible(true);
        $map = $property->getValue();
        
        $this->assertEquals(0, count($map), "WeakMap should be empty after Fiber is destroyed");
    }
}
