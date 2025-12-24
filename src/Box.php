<?php

declare(strict_types=1);

namespace Result;

class Box
{
    /**
     * The idea of this library is to use the enumerations functionality to
     * make it mandatory to handle the different states a return value can have when calling a method.
     *
     * It kind of replaces the try/catch keyword when calling a method. If there are errors you just write it
     * the error handling in a function and execute it if the state is Err when using the pattern matching.
     *
     * <code>
     *
     * /// Passing a bag as a return so that you make it mandatory to handle the error.
     * return Purse::put($item);
     *
     * /// Calling a method that returns a Result Enum.
     *  match (method_call()){
     *   case Result::Ok => other_function(),
     *   case Result::Error=> handle_error(),
     * };
     *
     *
     * </code>
     *
     * @param Item $bag
     * @return Result
     */
    public static function put(Item $bag): Result
    {
        try {
            $value = $bag->grab();
            return Result::Ok->hold($value);
        } catch (\Throwable $exception) {
            return Result::Error->hold($exception);
        }
    }
}
