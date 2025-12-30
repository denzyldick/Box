<?php

declare(strict_types=1);

namespace Result;

use Exception;
use Throwable;

enum Result
{
    case Ok;

    case Error;

    /**
     * This currenlty holds the object that should be called later.
     * At this moment only object works. I need to think if holding an array is usefull.
     * @param object|array $object
     * @return self
     */
    public function hold(array|object $object): self
    {
        if ($this === self::Ok && $object instanceof Throwable) {
            throw new \InvalidArgumentException("Result::Ok cannot hold an Exception. Use Result::Error instead.");
        }

        if ($this === self::Error && !($object instanceof Throwable)) {
            throw new \InvalidArgumentException("Result::Error must hold a Throwable (Exception/Error).");
        }

        Hold::push($object);
        return $this;
    }

    /**
     * Collect the real object from the bag.
     * @return Exception
     */
    public function exception(): Throwable
    {
        return Hold::pop();
    }

    public function collect(): object|array
    {
        return Hold::pop();
    }

    /**
     * @param callable $callback
     */
    /**
     * @param callable $callback
     */
    public function map(callable $callback): self
    {
        $value = Hold::pop();
        $newValue = is_array($value) ? array_map($callback, $value) : $callback($value);
        Hold::push($newValue);
        return $this;
    }

    /**
     * @param callable $callback Function that returns a Result
     */
    public function flatMap(callable $callback): self
    {
        // If we are Error, we skip (railway pattern)
        if ($this === self::Error) {
            return $this;
        }

        $value = Hold::pop();
        
        // Execute callback which should return a Result
        $newResult = $callback($value);
        
        // If the new result is OK, it might have held a value.
        // But wait, if $newResult is an Enum, we rely on IT having pushed to the stack.
        // This is tricky with the Global Hold pattern.
        
        // Example: $res->flatMap(fn($v) => Result::Ok->hold($v + 1));
        // The callback executes `Result::Ok->hold(...)`.
        // So the value is ALREADY on the stack! We don't need to do anything else.
        
        return $newResult;
    }

    /**
     * @param callable $callback Function to provide default value if Error
     */
    public function recover(callable $callback): self
    {
        if ($this === self::Ok) {
            return $this;
        }

        // We are Error. There might be an exception on stack, or nothing.
        // Let's assume there's an exception if we followed the pattern.
        try {
            $error = Hold::pop();
        } catch (\RuntimeException $e) {
            $error = null; // Stack empty, maybe just a bare Error
        }

        $newValue = $callback($error);
        
        // We convert to Ok with the new value
        return self::Ok->hold($newValue);
    }

    public function filter(callable $callback): self
    {
        Hold::push(array_filter(Hold::pop(), $callback));
        return $this;
    }

    public function isErr(): bool
    {
        return true;
    }

    public function isOk(): bool
    {
        return true;
    }

    public function unWrap(): array|object
    {
        return Hold::pop();
    }

    /**
     *
     */
    public function ok()
    {
    }

    public function err()
    {
    }
}
