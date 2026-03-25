<?php

declare(strict_types=1);

namespace Result;

use Throwable;
use JsonSerializable;
use Stringable;
use IteratorAggregate;
use Countable;
use Traversable;

enum ResultState: string
{
    case Ok = 'ok';
    case Error = 'error';
}

/**
 * @template T
 * @template E of Throwable
 * @implements IteratorAggregate<int, T>
 */
final readonly class Result implements JsonSerializable, Stringable, IteratorAggregate, Countable
{
    /**
     * @param T|E $value
     */
    private function __construct(
        private ResultState $state,
        private mixed $value
    ) {}

    /**
     * @template TValue
     * @param TValue $value
     * @return self<TValue, never>
     */
    public static function ok(mixed $value): self
    {
        if ($value instanceof Throwable) {
            throw new \InvalidArgumentException("Result::ok cannot hold a Throwable. Use Result::error instead.");
        }
        return new self(ResultState::Ok, $value);
    }

    /**
     * @template TError of Throwable
     * @param TError $error
     * @return self<never, TError>
     */
    public static function error(Throwable $error): self
    {
        return new self(ResultState::Error, $error);
    }

    /**
     * @template TValue
     * @param callable(): TValue $callback
     * @return self<TValue, Throwable>
     */
    public static function try(callable $callback): self
    {
        try {
            return self::ok($callback());
        } catch (Throwable $e) {
            return self::error($e);
        }
    }

    /**
     * @template TValue
     * @param bool $condition
     * @param TValue $value
     * @param Throwable $error
     * @return self<TValue, Throwable>
     */
    public static function when(bool $condition, mixed $value, Throwable $error): self
    {
        return $condition ? self::ok($value) : self::error($error);
    }

    /**
     * @template TV
     * @template TE of Throwable
     * @param array<self<TV, TE>> $results
     * @return self<array<TV>, TE>
     */
    public static function combine(array $results): self
    {
        $values = [];
        foreach ($results as $result) {
            if ($result->isErr()) {
                /** @var self<array<TV>, TE> $result */
                return $result;
            }
            $values[] = $result->collect();
        }
        return self::ok($values);
    }

    /**
     * @template TV
     * @template TE of Throwable
     * @param array<self<TV, TE>> $results
     * @return self<TV, TE>
     */
    public static function any(array $results): self
    {
        if (empty($results)) {
            return self::error(new \InvalidArgumentException("Cannot call any() on an empty array."));
        }

        foreach ($results as $result) {
            if ($result->isOk()) {
                return $result;
            }
        }

        return $results[0];
    }

    /**
     * @template TV
     * @template TE of Throwable
     * @param array<self<TV, TE>> $results
     * @return array{ok: array<TV>, error: array<TE>}
     */
    public static function partition(array $results): array
    {
        $partition = ['ok' => [], 'error' => []];
        foreach ($results as $result) {
            if ($result->isOk()) {
                $partition['ok'][] = $result->collect();
            } else {
                $partition['error'][] = $result->exception();
            }
        }
        return $partition;
    }

    public function isOk(): bool
    {
        return $this->state === ResultState::Ok;
    }

    public function isErr(): bool
    {
        return $this->state === ResultState::Error;
    }

    /**
     * @param callable(T): bool $predicate
     */
    public function isOkAnd(callable $predicate): bool
    {
        return $this->isOk() && $predicate($this->value);
    }

    /**
     * @param callable(E): bool $predicate
     */
    public function isErrAnd(callable $predicate): bool
    {
        return $this->isErr() && $predicate($this->value);
    }

    public function state(): ResultState
    {
        return $this->state;
    }

    /**
     * @return T
     */
    public function collect(): mixed
    {
        if ($this->isErr()) {
            throw new \RuntimeException("Cannot collect value from an Error result.");
        }
        return $this->value;
    }

    /**
     * @return E
     */
    public function exception(): Throwable
    {
        if ($this->isOk()) {
            throw new \RuntimeException("Cannot get exception from an Ok result.");
        }
        return $this->value;
    }

    /**
     * @return T
     * @throws E
     */
    public function unWrap(): mixed
    {
        if ($this->isErr()) {
            throw $this->value;
        }
        return $this->value;
    }

    /**
     * @return T
     */
    public function expect(string $message): mixed
    {
        if ($this->isErr()) {
            throw new \RuntimeException(sprintf('%s: %s', $message, $this->value->getMessage()), 0, $this->value);
        }
        return $this->value;
    }

    /**
     * @template TNew
     * @param callable(T): TNew $callback
     * @return self<TNew, E>
     */
    public function map(callable $callback): self
    {
        if ($this->isErr()) {
            /** @var self<TNew, E> */
            return $this;
        }

        return self::ok($callback($this->value));
    }

    /**
     * @template TNew
     * @param TNew $default
     * @param callable(T): TNew $callback
     * @return TNew
     */
    public function mapOr(mixed $default, callable $callback): mixed
    {
        return $this->isOk() ? $callback($this->value) : $default;
    }

    /**
     * @template TNew
     * @param callable(E): TNew $defaultCallback
     * @param callable(T): TNew $callback
     * @return TNew
     */
    public function mapOrElse(callable $defaultCallback, callable $callback): mixed
    {
        return $this->isOk() ? $callback($this->value) : $defaultCallback($this->value);
    }

    /**
     * @template TNew
     * @param callable(T): TNew $callback
     * @param bool $resetKeys
     * @return self<array<TNew>, E>
     */
    public function mapEach(callable $callback, bool $resetKeys = true): self
    {
        if ($this->isErr()) {
            /** @var self<array<TNew>, E> */
            return $this;
        }

        if (!is_iterable($this->value)) {
            throw new \LogicException("mapEach can only be called on a Result holding an iterable.");
        }

        $mapped = [];
        foreach ($this->value as $key => $item) {
            $mapped[$key] = $callback($item);
        }

        return self::ok($resetKeys ? array_values($mapped) : $mapped);
    }

    /**
     * @template TNew
     * @template ENew of Throwable
     * @param callable(T): Result<TNew, ENew> $callback
     * @return Result<TNew, E|ENew>
     */
    public function flatMap(callable $callback): self
    {
        if ($this->isErr()) {
            /** @var Result<TNew, E|ENew> */
            return $this;
        }

        $newResult = $callback($this->value);
        if (!$newResult instanceof self) {
            throw new \LogicException("flatMap callback must return a Result instance.");
        }

        return $newResult;
    }

    /**
     * @return T extends Result<mixed, Throwable> ? T : self<T, E>
     */
    public function flatten(): self
    {
        if ($this->isErr() || !($this->value instanceof self)) {
            return $this;
        }

        return $this->value;
    }

    /**
     * @param callable(E): T $callback
     * @return self<T, never>
     */
    public function recover(callable $callback): self
    {
        if ($this->isOk()) {
            /** @var self<T, never> */
            return $this;
        }

        return self::ok($callback($this->value));
    }

    /**
     * @template TNew
     * @template ENew of Throwable
     * @param callable(E): Result<TNew, ENew> $callback
     * @return Result<T|TNew, ENew>
     */
    public function orElse(callable $callback): self
    {
        if ($this->isOk()) {
            /** @var Result<T|TNew, ENew> */
            return $this;
        }

        return $callback($this->value);
    }

    /**
     * @template ENew of Throwable
     * @param callable(E): ENew $callback
     * @return self<T, ENew>
     */
    public function mapError(callable $callback): self
    {
        if ($this->isOk()) {
            /** @var self<T, ENew> */
            return $this;
        }
        $newError = $callback($this->value);
        if (!$newError instanceof Throwable) {
            throw new \LogicException("mapError callback must return a Throwable.");
        }
        return self::error($newError);
    }

    /**
     * @param callable(T): bool $predicate
     * @return self<T, E|Throwable>
     */
    public function filter(callable $predicate): self
    {
        if ($this->isErr()) {
            return $this;
        }

        if ($predicate($this->value)) {
            return $this;
        }

        return self::error(new \RuntimeException("Value filtered out."));
    }

    /**
     * @param callable(mixed): bool $predicate
     * @param bool $resetKeys
     * @return self<T, E>
     */
    public function filterEach(callable $predicate, bool $resetKeys = true): self
    {
        if ($this->isErr()) {
            return $this;
        }

        if (!is_iterable($this->value)) {
            throw new \LogicException("filterEach can only be called on a Result holding an iterable.");
        }

        $filtered = [];
        foreach ($this->value as $key => $item) {
            if ($predicate($item)) {
                $filtered[$key] = $item;
            }
        }

        return self::ok($resetKeys ? array_values($filtered) : $filtered);
    }

    /**
     * @template TRet
     * @param callable(T): TRet $onOk
     * @param callable(E): TRet $onErr
     * @return TRet
     */
    public function match(callable $onOk, callable $onErr): mixed
    {
        return $this->isOk() ? $onOk($this->value) : $onErr($this->value);
    }

    /**
     * @param callable(T): void $callback
     * @return $this
     */
    public function tapOk(callable $callback): self
    {
        if ($this->isOk()) {
            $callback($this->value);
        }
        return $this;
    }

    /**
     * @param callable(T): void $callback
     * @return $this
     */
    public function inspect(callable $callback): self
    {
        return $this->tapOk($callback);
    }

    /**
     * @param callable(E): void $callback
     * @return $this
     */
    public function tapErr(callable $callback): self
    {
        if ($this->isErr()) {
            $callback($this->value);
        }
        return $this;
    }

    /**
     * @param callable(E): void $callback
     * @return $this
     */
    public function inspectErr(callable $callback): self
    {
        return $this->tapErr($callback);
    }

    /**
     * @template TOther
     * @template EOther of Throwable
     * @param Result<TOther, EOther> $other
     * @return Result<array{T, TOther}, E|EOther>
     */
    public function zip(Result $other): self
    {
        if ($this->isErr()) {
            /** @var self<array{T, TOther}, E|EOther> */
            return $this;
        }
        if ($other->isErr()) {
            /** @var self<array{T, TOther}, E|EOther> */
            return $other;
        }
        return self::ok([$this->value, $other->collect()]);
    }

    /**
     * @template TOther
     * @template EOther of Throwable
     * @param Result<TOther, EOther> $other
     * @return Result<TOther, E|EOther>
     */
    public function and(Result $other): self
    {
        return $this->isOk() ? $other : $this;
    }

    /**
     * @template TOther
     * @template EOther of Throwable
     * @param Result<TOther, EOther> $other
     * @return Result<T|TOther, EOther>
     */
    public function or(Result $other): self
    {
        return $this->isOk() ? $this : $other;
    }

    /**
     * @return T|null
     */
    public function getValue(): mixed
    {
        return $this->isOk() ? $this->value : null;
    }

    /**
     * @return E|null
     */
    public function getError(): ?Throwable
    {
        return $this->isErr() ? $this->value : null;
    }

    /**
     * @param T $default
     * @return T
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $this->isOk() ? $this->value : $default;
    }

    /**
     * @param callable(E): T $op
     * @return T
     */
    public function unwrapOrElse(callable $op): mixed
    {
        return $this->isOk() ? $this->value : $op($this->value);
    }

    public function contains(mixed $value): bool
    {
        return $this->isOk() && $this->value === $value;
    }

    public function equals(self $other): bool
    {
        if ($this->state !== $other->state) {
            return false;
        }
        return $this->value === $other->value;
    }

    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state->value,
            'value' => $this->isOk() ? $this->value : $this->value->getMessage(),
        ];
    }

    public function __toString(): string
    {
        $valStr = is_object($this->value) 
            ? get_debug_type($this->value) 
            : var_export($this->value, true);

        return sprintf(
            'Result::%s(%s)',
            ucfirst($this->state->value),
            $this->isOk() ? $valStr : $this->value->getMessage()
        );
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        if ($this->isOk()) {
            yield $this->value;
        }
    }

    public function count(): int
    {
        return $this->isOk() ? 1 : 0;
    }
}
