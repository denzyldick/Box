# Result

`Result` is a modern, type-safe error handling library for PHP 8.1+. It replaces implicit, optional exceptions with an explicit **Result type** that forces callers to acknowledge both success and failure.

Inspired by Rust's `Result<T, E>` type, this library brings functional error handling and "railway oriented programming" to PHP with full support for **generics** (via PhpDoc), **immutability**, and **composition**.

---

## The Generic Chain

The library is designed around three components that work together:

```
Item<T>  ──>  Box::put(Item<T>)  ──>  Result<T, Throwable>
```

### `Item<T>` — The Input Contract

The `Item` interface represents an operation that can produce a value of type `T` or throw:

```php
/** @template T */
interface Item
{
    /** @return T */
    public function grab(): mixed;
}
```

### `Box::put()` — The Gateway

`Box::put()` calls `grab()` and wraps the result in a `Result`, catching any exceptions:

```php
/** @template TValue
 *  @param Item<TValue> $bag
 *  @return Result<TValue, Throwable>
 */
public static function put(Item $bag): Result
```

### `Result<T, E>` — The Output

A `Result` is either `Ok(T)` holding a value or `Error(E)` holding a `Throwable`. All methods are annotated with generics so your static analyser (PHPStan, Psalm, PhpStorm) can track types through chains of `map()`, `flatMap()`, etc.

### Wiring It Together

To preserve generic type information, your `Item` implementations **must** declare an `@implements` PhpDoc tag:

```php
use Result\Item;
use Result\Box;
use Result\Result;

use App\Model\User;
use App\Exception\NotFoundException;

/**
 * Implementing Item<User> tells Box::put() that
 * a successful Result will hold a User object.
 *
 * @implements Item<User>
 */
class UserFetcher implements Item
{
    public function __construct(
        private int $id,
        private Database $db,
    ) {}

    /**
     * @return User
     * @throws NotFoundException
     */
    public function grab(): mixed
    {
        $user = $this->db->findUser($this->id);

        if ($user === null) {
            throw new NotFoundException("User {$this->id} not found");
        }

        return $user;
    }
}
```

Now calling `Box::put()` returns `Result<User, Throwable>` and your tooling knows the type:

```php
/**
 * The generic is preserved through the entire chain.
 *
 * @return Result<User, Throwable>     ← inferred from @implements
 */
function fetchUser(int $id): Result
{
    return Box::put(new UserFetcher($id, $this->db));
}
```

---

## Creating Results

```php
// Direct creation
$ok  = Result::ok("Happy value");          // Result<string, never>
$err = Result::error(new Exception("Sad")); // Result<never, Exception>

// Wrap any callable that might throw
$res = Result::try(fn() => $this->riskyOp()); // Result<mixed, Throwable>

// Conditional factory
$res = Result::when(
    $age >= 18, "Access Granted", new Exception("Too young")
);
```

---

## Working with Results

### Pattern Matching (Explicit)

```php
$message = match ($result->state()) {
    ResultState::Ok    => $result->collect()->name,
    ResultState::Error => "Error: " . $result->exception()->getMessage(),
};
```

### Railway (Implicit)

```php
$nickname = fetchUser(123)
    ->map(fn(User $u) => $u->name)
    ->map(fn(string $name) => strtolower($name))
    ->unwrapOr("guest");
```

### Match (Functional)

```php
$result->match(
    onOk:  fn(User $u) => $u->name,
    onErr: fn($e)      => "fallback",
);
```

---

## Safe Unwrapping

```php
$val = $res->collect();                 // T | throws if Error
$val = $res->unwrapOr("default");       // T | default on Error
$val = $res->unwrapOrElse(fn($e) => ..);// T | lazy default
$val = $res->expect("Must exist");      // T | custom message
```

---

## Transformation Reference

| Method | Input | Output | Description |
|---|---|---|---|
| `map(callable)` | `Result<T, E>` | `Result<TNew, E>` | Transform the ok value, skip on error |
| `mapOr(default, callable)` | `Result<T, E>` | `TNew` | Map ok value or return default |
| `mapOrElse(errCb, okCb)` | `Result<T, E>` | `TNew` | Map either variant to a value |
| `mapEach(callable)` | `Result<iterable<T>, E>` | `Result<array<TNew>, E>` | Map each element of the iterable |
| `flatMap(callable)` | `Result<T, E>` | `Result<TNew, E>` | Chain another Result-returning operation |
| `flatten()` | `Result<Result<T, E>, E>` | `Result<T, E>` | Flatten nested Results |
| `mapError(callable)` | `Result<T, E>` | `Result<T, ENew>` | Transform the error |
| `recover(callable)` | `Result<T, E>` | `Result<T, never>` | Recover from error (turns Error→Ok) |
| `orElse(callable)` | `Result<T, E>` | `Result<TNew, ENew>` | Recover with another Result |
| `filter(callable)` | `Result<T, E>` | `Result<T, E>` | Error if predicate fails |
| `filterEach(callable)` | `Result<iterable<T>, E>` | `Result<array<T>, E>` | Filter elements of the iterable |

---

## Batch Operations

```php
// Fail-fast — Ok only if all are Ok
$combined = Result::combine([$r1, $r2, $r3]);

// First success — returns first Ok, or last Error
$any = Result::any([$r1, $r2, $r3]);

// Collect into {ok: [...], error: [...]}
$partitioned = Result::partition([$r1, $r2, $r3]);
```

---

## Logical Composition

```php
$tuple = $r1->zip($r2);  // Result<[A, B], E>
$res   = $r1->and($r2);  // $r2 if Ok, else $r1
$res   = $r1->or($r2);   // $r1 if Ok, else $r2
```

---

## Side-Effects

```php
$result
    ->tapOk(fn($v) => Logger::info("Success: $v"))
    ->tapErr(fn($e) => Logger::error("Fail: " . $e->getMessage()));
```

Aliases: `inspect()` = `tapOk()`, `inspectErr()` = `tapErr()`.

---

## Interoperability

```php
// JSON
json_encode(Result::ok("hi")); // {"state":"ok","value":"hi"}

// Iteration (0 or 1 items)
foreach ($result as $value) { echo $value; }

// Debug
echo (string) $result; // Result::Ok('hi')
```

---

## Installation

```bash
composer require denzyl/result
```

---

## Philosophy

> "If an error can happen, the caller should have to look at it."

This library does not pretend PHP has checked exceptions. It simply provides a better failure model than `throw`-and-pray by making your API contracts honest and your code's intent explicit.

Because PHP lacks native generics, **PhpDoc annotations are the glue that makes type inference work**. Always add `@implements Item<YourType>` to your Item classes so your IDE and static analysers can trace the generic chain from `Box::put()` through to the final `Result`.
