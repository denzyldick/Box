# Result

`Result` is a modern, type-safe error handling library for PHP 8.2+. It replaces implicit, optional exceptions with an explicit **Result type** that forces callers to acknowledge both success and failure.

Inspired by Rust's `Result<T, E>` type, this library brings functional error handling and "railway oriented programming" to PHP with full support for **generics** (via PhpDoc), **immutability**, and **composition**.

---

## Why a Result Type?

Traditional PHP error handling has two common patterns, both flawed:

| Pattern | Problem |
|---|---|
| **Exceptions** | Uncaught exceptions crash the program. Nothing in the signature tells the caller an error can happen — you have to read the implementation or docs. |
| **Returning null/false** | Silent failures. The caller can forget to check. No error context — you lose *why* it failed. |

A `Result` solves both: the return type is always `Result<T, E>`, so the caller **must** handle success and failure. The error variant carries the full `Throwable`, so nothing is lost. And since PHP has no native generics, PhpDoc annotations make the chain traceable for your IDE and static analysers.

---

## The Generic Chain

The library is designed around three components that work together:

```
Item<T>  ──>  Box::put(Item<T>)  ──>  Result<T, Throwable>
```

This split exists **by design**: separating the "what could go wrong" (the `Item` that may throw) from the "what do I do with the result" (the `Result`). You keep your domain logic clean (just throw when something's wrong) and get a functional, type-safe result on the other side.

### `Item<T>` — The Input Contract

The `Item` interface represents an operation that can produce a value of type `T` or throw. It's deliberately minimal: write your domain logic naturally, and let `Box::put()` convert it into a `Result`.

```php
/** @template T */
interface Item
{
    /** @return T */
    public function grab(): mixed;
}
```

**Why `Item` exists:** Instead of wrapping every call in `try/catch` yourself, you declare "this is a fallible operation" by implementing `Item`. `Box::put()` handles the boilerplate of catching exceptions and wrapping them in an `Error` for you.

### `Box::put()` — The Gateway

`Box::put()` calls `grab()` and wraps the result in a `Result`, catching any exceptions:

```php
/** @template TValue
 *  @param Item<TValue> $bag
 *  @return Result<TValue, Throwable>
 */
public static function put(Item $bag): Result
```

**Why `Box::put()`:** It's the bridge between imperative domain code (which throws) and the functional result pipeline. Your `grab()` method throws naturally when something's wrong — `Box::put()` captures that throwable and turns it into an `Error` variant, keeping the API honest without forcing you to manually construct `Result::error()` everywhere.

### `Result<T, E>` — The Output

A `Result` is either `Ok(T)` holding a value or `Error(E)` holding a `Throwable`. All methods are annotated with generics so your static analyser (PHPStan, Psalm, PhpStorm) can track types through chains of `map()`, `flatMap()`, etc.

**Why `Result` is immutable:** Every transformation (`map`, `filter`, `flatMap`, etc.) returns a *new* `Result` instance. This means you can safely share results across functions without worrying about mutation bugs. The original value is never modified.

### Wiring It Together

To preserve generic type information, your `Item` implementations **must** declare an `@implements` PhpDoc tag:

```php
use Result\Item;
use Result\Box;
use Result\Result;

use App\Model\User;
use App\Exception\NotFoundException;

/**
 * @implements Item<User>
 */
class UserFetcher implements Item
{
    public function __construct(
        private int $id,
        private Database $db,
    ) {}

    /** @return User */
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

Now calling `Box::put()` returns `Result<User, Throwable>`:

```php
/**
 * @return Result<User, Throwable>
 */
function fetchUser(int $id): Result
{
    return Box::put(new UserFetcher($id, $this->db));
}
```

---

## Creating Results

```php
// Direct — when you already have the value or error
$ok  = Result::ok("Happy value");            // Result<string, never>
$err = Result::error(new Exception("Sad"));  // Result<never, Exception>

// Try — wrap any callable that might throw
$res = Result::try(fn() => $this->riskyOp());

// When — conditional result without an if/else
$res = Result::when($age >= 18, "Access Granted", new Exception("Too young"));
```

**Why choose one over the other?**

| Method | Use when... |
|---|---|
| `Result::ok()` | You already have a value and know it's valid |
| `Result::error()` | You already have a `Throwable` and want to represent failure |
| `Result::try()` | You have a callable that might throw and want to capture any exception automatically |
| `Result::when()` | You have a boolean condition and want to branch into Ok or Error in one expression — more concise than `if/else` |

---

## Working with Results

There are three styles, each with a different trade-off. Pick the one that fits your context.

### Pattern Matching (Explicit)

```php
$message = match ($result->state()) {
    ResultState::Ok    => $result->collect()->name,
    ResultState::Error => "Error: " . $result->exception()->getMessage(),
};
```

**Why use this:** When you need to handle both branches with completely different logic, or when your handler is complex enough that readability suffers from nested callbacks. The `match` expression forces you to enumerate all cases — the compiler won't let you forget the `Error` branch. Best for: **controller-level code** where you're translating domain results into HTTP responses or UI state.

### Railway (Implicit / Chain)

```php
$nickname = fetchUser(123)
    ->map(fn(User $u) => $u->name)
    ->map(fn(string $name) => strtolower($name))
    ->unwrapOr("guest");
```

**Why use this:** When you only care about the success path and have a reasonable fallback for failure. Each `map()` only runs if the result is `Ok` — errors pass through silently. This is "railway oriented programming": the happy path runs along one track, the error track bypasses all operations. Best for: **data transformation pipelines** (parsing, validation, cleanup) where you're threading a value through a series of steps.

### Match (Functional)

```php
$result->match(
    onOk:  fn(User $u) => $u->name,
    onErr: fn($e)      => "fallback",
);
```

**Why use this:** More explicit than railway, more concise than `match ($result->state())`. Unlike the railway pattern, both branches are visible in one place. Unlike pattern matching, you don't need to import `ResultState`. Best for: **one-off handlers** where you want both branches visible but the logic is simple enough that a full `match` statement feels verbose.

### Compare the Three

| Style | Verbosity | Both branches visible? | Best for |
|---|---|---|---|
| Pattern matching | High | Yes | Complex branching, API/UI handlers |
| Railway | Low | No (chain assumes success) | Data pipelines, transformations |
| Functional match | Medium | Yes | Simple handlers, callbacks |

---

## Safe Unwrapping

These methods extract the inner value. They differ in what happens when the result is an `Error`.

```php
$val = $res->collect();                     // throws RuntimeException if Error
$val = $res->unwrapOr("default");           // returns "default" if Error
$val = $res->unwrapOrElse(fn($e) => ...);   // calls closure with the error if Error
$val = $res->expect("User must exist");     // throws with your message if Error
```

**Why so many?** Each serves a different failure-handling philosophy:

| Method | When to use |
|---|---|
| `collect()` | When you *know* it must succeed (e.g. after `filter` + `recover`). Throws if you're wrong — it's a safety net for bugs in your logic, not a control flow mechanism. |
| `unwrapOr($default)` | When you have a simple static fallback. Use for: "default config value", "empty string", "0". |
| `unwrapOrElse(callable)` | When computing the fallback is expensive or requires the error context. The callable receives the `Throwable` so you can log it, format it, etc. Unlike `unwrapOr`, the fallback is **lazy** — it only runs if the result is an error. |
| `expect($message)` | Like `collect()` but with a custom error message that helps debugging. Use when the default `RuntimeException` message isn't descriptive enough. |

---

## Side-Effects (Tap)

Sometimes you want to do something with the value without transforming it — logging, caching, metrics.

```php
$result
    ->tapOk(fn($v) => Logger::info("Success: $v"))
    ->tapErr(fn($e) => Logger::error("Fail: " . $e->getMessage()));
```

Aliases: `inspect()` = `tapOk()`, `inspectErr()` = `tapErr()`.

**Why `tapOk` / `tapErr` exist:** `map()` implies transformation and should be pure. When you're just observing (logging, emitting events, incrementing counters), use `tapOk`/`tapErr`. They pass the result through unchanged — they're **side-effect windows** in an otherwise pure pipeline. The alias `inspect()` exists for familiarity if you come from Rust.

**The key difference from `map()`:**
- `map()` transforms the value and returns a new `Result`
- `tapOk()` runs a callback and returns the *same* `Result` instance (no transformation)

---

## Transforming Collections

When your `Result` holds an iterable, these methods let you work with the elements.

```php
$active = Result::ok($userList)
    ->filterEach(fn(User $u) => $u->isActive)
    ->mapEach(fn(User $u) => $u->email)
    ->collect();
```

**`mapEach(callable, $resetKeys = true)`** — transforms every element of the iterable. If `$resetKeys` is true, the returned array has sequential numeric keys (like `array_values()`). Set `$resetKeys = false` to preserve string or non-sequential keys.

**`filterEach(callable, $resetKeys = true)`** — keeps only elements where the predicate returns truthy.

**Why seperate methods?** Calling `map()` on a `Result` takes you from `Result<T, E>` to `Result<TNew, E>`. If `T` happens to be an array and you want to map its elements, you'd need to write `->map(fn(array $items) => array_map(...))` manually every time. `mapEach`/`filterEach` are convenience methods that skip the boilerplate. They also **only work on iterables** and throw `LogicException` otherwise — catching misuse early.

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

### Deep Dive: `flatMap`

```php
Result::ok(2)
    ->flatMap(fn(int $n) => match (true) {
        $n > 0 => Result::ok($n * 2),
        default => Result::error(new Exception("Must be positive")),
    });
```

**Why `flatMap` over `map`?** `map` wraps the callback's return in a new `Ok`. If your callback already returns a `Result`, you'd get `Result<Result<TNew, ENew>, E>` — a nested result. `flatMap` avoids this nesting by expecting the callback itself to return a `Result`. Use `flatMap` when your callback *deliberately* decides success/failure (the inner logic can also produce an error). Use `map` when the inner logic is infallible and only transforms.

| | Callback returns `TNew` | Callback returns `Result<TNew, ENew>` |
|---|---|---|
| Use `map` | Yes — wraps in `Ok` automatically | Avoid — creates nesting |
| Use `flatMap` | Errors (expects `Result`) | Yes — no nesting |

---

### Deep Dive: `recover` vs `orElse` vs `mapError`

All three handle errors, but with different goals:

```php
// recover: turn error back into success with a computed value
$result->recover(fn(Exception $e) => "Fallback for: " . $e->getMessage());
// Result<never, Exception> → Result<string, never>

// orElse: turn error into a new Result (might still be an error)
$result->orElse(fn(Exception $e) => Result::error(new LoggedException($e)));
// Result<T, Exception> → Result<T, LoggedException>

// mapError: transform the error without changing Ok/Error state
$result->mapError(fn(Exception $e) => new DomainException("Wrapper", 0, $e));
// Result<T, Exception> → Result<T, DomainException>
```

| Method | Ok passes through? | Error becomes... |
|---|---|---|
| `recover` | Yes (unchanged) | New `Ok` value |
| `orElse` | Yes (unchanged) | Whatever `Result` the callback returns |
| `mapError` | Yes (unchanged) | Same state, transformed error |

---

## Batch Operations

When you have multiple independent results and need to combine them.

```php
// combine: fail-fast, get all values or first error
$combined = Result::combine([$r1, $r2, $r3]);

// any: first success, or last error if all failed
$any = Result::any([$r1, $r2, $r3]);

// partition: split into successes and failures
$partitioned = Result::partition([$r1, $r2, $r3]);
```

**Why three different batch methods?**

| Method | Returns | Use when... |
|---|---|---|
| `combine()` | `Result<array, E>` | You need *all* results to proceed. Validation of a form with multiple fields — one field fails, the whole form fails. |
| `any()` | `Result<T, E>` | You only need *one* success. Trying multiple API endpoints or cache replicas — the first one that works is enough. |
| `partition()` | `{ok: array, error: array}` | You want to process both successes and failures independently. Bulk operations where you need to report partial failures. |

---

## Logical Composition

```php
$tuple = $r1->zip($r2);      // Result<[A, B], E> — pair two results
$res   = $r1->and($r2);      // $r2 if Ok, else $r1 — sequential dependency
$res   = $r1->or($r2);       // $r1 if Ok, else $r2 — fallback
```

**Why these matter:**

- **`zip($other)`** — when you need to combine two independent results into a tuple. Unlike `combine()`, `zip()` works on pairs and preserves the individual types `[A, B]` rather than flattening into a generic array.

- **`and($other)`** — "run this next only if I succeeded." Short-circuits on the first error. Use when operations are **sequential and dependent**: validate input → save to DB → send email. If validation fails, the email is never sent.

- **`or($other)`** — "try this if I failed." Short-circuits on the first success. Use when you have **alternatives**: load from cache → if missing, load from DB → if still missing, fetch from API.

---

## Interoperability

```php
// JSON — useful for API responses
json_encode(Result::ok("hi"));            // {"state":"ok","value":"hi"}
json_encode(Result::error(new Exception("e")));  // {"state":"error","value":"e"}

// Iteration — treat Result as a collection of 0 or 1 items
foreach ($result as $value) { echo $value; }

// Debug — quick string representation
echo (string) $result;                     // Result::Ok('hi')
```

---

## Installation

```bash
composer require denzyl/result
```

## Development

```bash
composer install
composer ci
```

The release gate validates Composer metadata, checks Mago formatting, runs Mago linting, runs Phanalist against `src/`, and executes the PHPUnit suite. For a local Phanalist checkout, run:

```bash
PHANALIST_BIN=../phanalist/phanalist composer phanalist
```

---

## Philosophy

> "If an error can happen, the caller should have to look at it."

This library does not pretend PHP has checked exceptions. It simply provides a better failure model than `throw`-and-pray by making your API contracts honest and your code's intent explicit.

Because PHP lacks native generics, **PhpDoc annotations are the glue that makes type inference work**. Always add `@implements Item<YourType>` to your Item classes so your IDE and static analysers can trace the generic chain from `Box::put()` through to the final `Result`.
