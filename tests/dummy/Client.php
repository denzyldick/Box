<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Result\Box;
use Result\Item;
use Result\Result;

/**
 * @implements Item<Response>
 */
class Client implements Item
{
    public static bool $simulateError = false;

    /**
     * @return Result<Response, \Throwable>
     */
    public function get(): Result
    {
        return Box::put($this);
    }

    public function grab(): Response
    {
        if (static::$simulateError) {
            throw new \Exception('Dummy Exception.');
        }

        return new Response('Hello world');
    }

    public function list()
    {
        return Result::ok([
            [
                'name' => 'hello',
                'active' => true,
            ],
            [
                'name' => 'world',
                'active' => false,
            ],
        ]);
    }
}
