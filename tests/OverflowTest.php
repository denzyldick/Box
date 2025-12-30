<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\Hold;

class OverflowTest extends TestCase
{
    public function setUp(): void
    {
        Hold::reset();
    }

    public function test_it_throws_overflow_exception_when_limit_reached()
    {
        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Result hold stack exceeded maximum size of 50');

        // Push 51 items
        for ($i = 0; $i <= 51; $i++) {
            Result::Ok->hold((object)['id' => $i]);
        }
    }
}
