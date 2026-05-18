<?php

use PHPUnit\Framework\TestCase;
use Result\Result;
use Result\ResultState;
use Tests\Dummy\Client;
use Tests\Dummy\OrderService;

/**
 */
class ResultTest extends TestCase
{
    /**
     * Test the result error handling.
     *
     * @return void
     */
    public function testResult(): void
    {
        $client = new Client();
        $result = $client->get();

        $amount = match ($result->state()) {
            ResultState::Ok => function () use ($result) {
                $service = new OrderService();

                return $service->handle($result->collect());
            },
            ResultState::Error => function () {
                return 0;
            },
        };

        $actual = 'Hello world';
        $this->assertEquals($amount(), $actual);
    }

    /**
     * Test the result error handling.
     *
     * @return void
     */
    public function testIgnoreException(): void
    {
        $client = new Client();
        $client::$simulateError = true;
        $result = $client->get();

        $amount = match ($result->state()) {
            ResultState::Ok => function () use ($result) {
                $collected = $result->collect();
                $service = new OrderService();

                return $service->handle($collected);
            },
            ResultState::Error => function () {
                return 0;
            },
        };

        $actual = 0;
        $this->assertEquals($amount(), $actual);
    }

    public function testException()
    {
        $client = new Client();
        $client::$simulateError = true;
        $result = $client->get();

        $error = match ($result->state()) {
            ResultState::Ok => function () {},
            ResultState::Error => function () use ($result) {
                return $result->exception();
            },
        };

        $message = 'Dummy Exception.';
        $this->assertEquals($message, $error()->getMessage());
    }

    public function testMapTranformator()
    {
        $users = Result::ok([
            [
                'name' => 'hello',
                'active' => true,
            ],
            [
                'name' => 'world',
                'active' => false,
            ],
        ]);

        $t = $users->mapEach(function ($item) {
            $item['active'] = true;
            return $item;
        })->unWrap();
        $this->assertEquals(true, $t[0]['active']);
        $this->assertEquals(true, $t[1]['active']);
    }

    public function testFilterTranformator()
    {
        $active = Result::ok([
            true,
            false,
            true,
        ])->filterEach(function ($item) {
            return $item;
        })->unWrap();
        $this->assertEquals(2, count($active));
    }
}
