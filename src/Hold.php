<?php

declare(strict_types=1);

namespace Result;

final class Hold
{
    private const MAX_SIZE = 50;

    /** @var \WeakMap<object, array> */
    private static \WeakMap $map;

    public static function push(array|object $object): void
    {
        $key = self::getStorageKey();

        self::initMap();

        $stack = self::$map[$key] ?? [];

        if (count($stack) >= self::MAX_SIZE) {
            throw new \OverflowException("Result hold stack exceeded maximum size of " . self::MAX_SIZE);
        }

        $stack[] = $object;
        self::$map[$key] = $stack;
    }

    /**
     * Pop the last element in the array for the current Fiber.
     * @return object|array
     */
    public static function pop(): array|object
    {
        $key = self::getStorageKey();
        self::initMap();

        $stack = self::$map[$key] ?? [];

        if (empty($stack)) {
            throw new \RuntimeException("Result holder is empty for this Fiber context");
        }

        $item = array_pop($stack);
        self::$map[$key] = $stack;

        return $item;
    }

    /**
     * Clear all held items (useful for testing)
     */
    public static function reset(): void
    {
        self::$map = new \WeakMap();
    }

    public static function count(): int
    {
        $key = self::getStorageKey();
        self::initMap();
        return isset(self::$map[$key]) ? count(self::$map[$key]) : 0;
    }

    private static function getStorageKey(): object
    {
        return \Fiber::getCurrent() ?? self::getMainFiber();
    }

    /**
     * Static storage for the main thread object since it's not a real Fiber
     */
    private static ?object $mainFiber = null;

    private static function getMainFiber(): object
    {
        return self::$mainFiber ??= new class () {};
    }

    private static function initMap(): void
    {
        if (!isset(self::$map)) {
            self::$map = new \WeakMap();
        }
    }

    /**
     * Get the type of the last item in the array.
     */
    public static function type()
    {
    }
}
