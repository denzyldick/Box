<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\ResultState;

class FunctionalTest extends TestCase
{
    public function test_try_captures_exception()
    {
        $res = Result::try(fn() => throw new \Exception('boom'));
        $this->assertTrue($res->isErr());
        $this->assertEquals('boom', $res->exception()->getMessage());
    }

    public function test_try_captures_value()
    {
        $res = Result::try(fn() => 42);
        $this->assertTrue($res->isOk());
        $this->assertEquals(42, $res->collect());
    }

    public function test_when_factory()
    {
        $ok = Result::when(true, 'yes', new \Exception('no'));
        $err = Result::when(false, 'yes', new \Exception('no'));

        $this->assertTrue($ok->isOk());
        $this->assertTrue($err->isErr());
    }

    public function test_combine_all_ok()
    {
        $results = [Result::ok(1), Result::ok(2), Result::ok(3)];
        $combined = Result::combine($results);

        $this->assertEquals([1, 2, 3], $combined->collect());
    }

    public function test_combine_short_circuits()
    {
        $results = [Result::ok(1), Result::error(new \Exception('fail')), Result::ok(3)];
        $combined = Result::combine($results);

        $this->assertTrue($combined->isErr());
        $this->assertEquals('fail', $combined->exception()->getMessage());
    }

    public function test_partition()
    {
        $results = [Result::ok(1), Result::error(new \Exception('e1')), Result::ok(2)];
        $partitioned = Result::partition($results);

        $this->assertEquals([1, 2], $partitioned['ok']);
        $this->assertEquals('e1', $partitioned['error'][0]->getMessage());
    }

    public function test_zip()
    {
        $r1 = Result::ok(1);
        $r2 = Result::ok(2);
        $zipped = $r1->zip($r2);

        $this->assertEquals([1, 2], $zipped->collect());
    }

    public function test_is_ok_and()
    {
        $res = Result::ok(10);
        $this->assertTrue($res->isOkAnd(fn($v) => $v > 5));
        $this->assertFalse($res->isOkAnd(fn($v) => $v < 5));
    }

    public function test_unwrap_or_else()
    {
        $res = Result::error(new \Exception('err'));
        $val = $res->unwrapOrElse(fn($e) => 'recovered');
        $this->assertEquals('recovered', $val);
    }

    public function test_iterator()
    {
        $res = Result::ok('hello');
        $items = iterator_to_array($res);
        $this->assertEquals(['hello'], $items);

        $err = Result::error(new \Exception());
        $this->assertEquals([], iterator_to_array($err));
    }

    public function test_json_serialize()
    {
        $res = Result::ok('data');
        $json = json_encode($res);
        $this->assertStringContainsString('"state":"ok"', $json);
        $this->assertStringContainsString('"value":"data"', $json);
    }
}
