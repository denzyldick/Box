<?php

use Result\Result;
use PHPUnit\Framework\TestCase;
use Tests\Dummy\Client;
use Tests\Dummy\OrderService;
use Tests\Dummy\OrderException;

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

        $amount = match ($client->get()) {
            Result::Ok => function () {
                return (new OrderService())->handle(Result::Ok->collect());
            },
            Result::Error => function () {
                return 0;
            }
        };

        $actual = "Hello world";
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
        $amount = match ($client->get()) {
            Result::Ok => function () {

                $collected = Result::Ok->collect();
                return (new OrderService())->handle($collected);
            },
            Result::Error => function () {
                return 0;
            }
        };

        $actual = 0;
        $this->assertEquals($amount(), $actual);
    }

    public function testException()
    {
        $client = new Client();

        $client::$simulateError = true;

        $error = match ($client->get()) {
            Result::Ok => function () {},
            Result::Error => function () {
                return Result::Error->exception();
            }
        };

        $message = "Dummy Exception.";
        $this->assertEquals($message, $error()->getMessage());
    }

    public function testMapTranformator()
    {
        $users = Result::Ok->hold([
          [
            'name' => 'hello',
            'active' => true,
          ],
          [
            'name' => 'world',
            'active' => false,
          ]
        ]);

        $t = $users->map(function ($item) {
            $item['active'] = true;
            return $item;
        })->unwrap();
        $this->assertEquals(true, $t[0]['active']);
        $this->assertEquals(true, $t[1]['active']);
    }

    public function testFilterTranformator()
    {
        $active = Result::Ok->hold([
          true,
          false,
          true
        ])->filter(function ($item) {

            return $item;
        })->unwrap();
        $this->assertEquals(count($active), 2);
    }
}
