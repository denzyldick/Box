<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;

class TransformTest extends TestCase
{
    protected function setUp(): void
    {
        Hold::reset();
    }

    public function test_map_works_with_single_object()
    {
        $val = (object)['n' => 1];
        
        $res = Result::Ok->hold($val)
            ->map(fn($o) => (object)['n' => $o->n + 1])
            ->collect();
            
        $this->assertEquals(2, $res->n);
    }

    public function test_flat_map_chains_results()
    {
        // 1 -> Ok(2) -> Ok(4)
        $res = Result::Ok->hold((object)['n' => 1])
            ->flatMap(function($o) {
                return Result::Ok->hold((object)['n' => $o->n * 2]);
            })
            ->flatMap(function($o) {
                return Result::Ok->hold((object)['n' => $o->n * 2]);
            });
            
        $this->assertEquals(Result::Ok, $res);
        $this->assertEquals(4, $res->collect()->n);
    }

    public function test_flat_map_short_circuits_on_error()
    {
        $res = Result::Error->hold(new \Exception('fail'))
            ->flatMap(function($o) {
                $this->fail("Should not execute this");
                return Result::Ok->hold($o);
            });
            
        $this->assertEquals(Result::Error, $res);
        // Error should still be on stack
        $this->assertEquals('fail', $res->exception()->getMessage());
    }

    public function test_recover_turns_error_into_ok()
    {
        $res = Result::Error->hold(new \Exception('fail'))
            ->recover(fn($e) => (object)['msg' => 'recovered: ' . $e->getMessage()]);
            
        $this->assertEquals(Result::Ok, $res);
        $this->assertEquals('recovered: fail', $res->collect()->msg);
    }

    public function test_recover_is_skipped_if_ok()
    {
        $res = Result::Ok->hold((object)['val' => 'good'])
            ->recover(fn($e) => (object)['val' => 'bad']);
            
        $this->assertEquals(Result::Ok, $res);
        $this->assertEquals('good', $res->collect()->val);
    }
}
