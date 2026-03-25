<?php

declare(strict_types=1);

namespace Result;

use Throwable;

class Box
{
    /**
     * The idea of this library is to use the Result type to
     * make it mandatory to handle the different states a return value can have.
     *
     * It kind of replaces the try/catch keyword. If there are errors, you handle
     * them via functional methods (map, recover, etc.) or pattern matching.
     *
     * <code>
     *
     * /// Wrapping an operation that might fail
     * return Box::put($item);
     *
     * /// Calling a method that returns a Result.
     *  match ($result->state()) {
     *   ResultState::Ok => other_function($result->collect()),
     *   ResultState::Error => handle_error($result->exception()),
     * };
     *
     *
     * </code>
     *
     * @template TValue
     * @param Item<TValue> $bag
     * @return Result<TValue, Throwable>
     */
    public static function put(Item $bag): Result
    {
        try {
            return Result::ok($bag->grab());
        } catch (\Throwable $exception) {
            return Result::error($exception);
        }
    }
}
