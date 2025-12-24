<?php

namespace Tests\Dummy;

use Result\Box;
use Result\Item;
use Result\Result;
use UserList;

class Client implements Item
{
    public static bool $simulateError = false;

    /**
     *
     */
    public function get(): Result
    {
        return Box::put($this);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function grab(): Response
    {
        if (static::$simulateError) {
            throw new \Exception('Dummy Exception.');
        }

        return new Response('Hello world');
    }

    public function list()
    {
        return Result::Ok->hold([
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
