<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\ResultState;

class TransformTest extends TestCase
{
    public function test_map_works_with_single_object()
    {
        $val = (object) ['n' => 1];

        $res = Result::ok($val)->map(fn($o) => (object) ['n' => $o->n + 1])->collect();

        $this->assertEquals(2, $res->n);
    }

    public function test_flat_map_chains_results()
    {
        // 1 -> Ok(2) -> Ok(4)
        $res = Result::ok((object) ['n' => 1])->flatMap(function ($o) {
            return Result::ok((object) ['n' => $o->n * 2]);
        })->flatMap(function ($o) {
            return Result::ok((object) ['n' => $o->n * 2]);
        });

        $this->assertEquals(ResultState::Ok, $res->state());
        $this->assertEquals(4, $res->collect()->n);
    }

    public function test_flat_map_short_circuits_on_error()
    {
        $res = Result::error(new \Exception('fail'))->flatMap(function ($o) {
            $this->fail('Should not execute this');
            return Result::ok($o);
        });

        $this->assertEquals(ResultState::Error, $res->state());
        $this->assertEquals('fail', $res->exception()->getMessage());
    }

    public function test_recover_turns_error_into_ok()
    {
        $res = Result::error(new \Exception('fail'))->recover(fn($e) => (object) [
            'msg' => 'recovered: ' . $e->getMessage(),
        ]);

        $this->assertEquals(ResultState::Ok, $res->state());
        $this->assertEquals('recovered: fail', $res->collect()->msg);
    }

    public function test_recover_is_skipped_if_ok()
    {
        $res = Result::ok((object) ['val' => 'good'])->recover(fn($e) => (object) ['val' => 'bad']);

        $this->assertEquals(ResultState::Ok, $res->state());
        $this->assertEquals('good', $res->collect()->val);
    }
}
