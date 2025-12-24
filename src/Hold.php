<?php

namespace Result;

final class Hold
{
    public static array $map = [];

    /**
     * Pop the last element in the array.
     * @return object
     */
    public static function pop(): array|object
    {
        return array_pop(self::$map);
    }

    /**
     * Get the type of the last item in the array.
     */
    public static function type()
    {
    }
}
