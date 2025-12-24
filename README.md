# Box

Box is a small pattern/library I have been using internally to change how errors are handled in PHP.

It addresses a fundamental limitation of PHP’s exception model: **you cannot make error handling mandatory for the caller**.

There is nothing _wrong_ with exceptions themselves, but they are **optional by design**. A caller can always ignore them, intentionally or accidentally.

Box exists to make that choice explicit.

---

## The Problem

Consider a typical PHP class:

```php
<?php

class Client {
    public function getSomething(): string {
        // ...
        throw new Exception("Hello world");
    }
}
```

Somewhere else in the codebase:

```php
<?php

$something = (new Client())->getSomething();
echo $something;
```

As the author of `Client`, you **cannot enforce** that the caller handles the error.

Yes, a `try/catch` _could_ be used — but nothing forces the caller to do so. If the exception is thrown and not caught, the program crashes. If the caller chooses to “only code the happy path”, the type system offers no resistance.

This is not a tooling issue. It is a language-level limitation.

---

## What Box Changes

Box replaces implicit, optional error handling with **explicit, mandatory handling**.

Instead of throwing, a method returns a value that represents **both success and failure**. The caller must inspect it.

```php
$something = match ((new Client())->getSomething()) {
    Result::Ok => function () {
        echo Result::Ok->collect();
    },
    Result::Error => function () {
        // error must be acknowledged here
    }
};
```

This forces the caller to make a decision:

- Handle the error
- Explicitly ignore it

But **never accidentally skip it**.

---

## Why This Matters

- No hidden control flow
- No unhandled runtime crashes
- Error handling becomes part of the API contract
- Intent is explicit and visible at the call site

This pattern is inspired by `Result`-based error handling found in languages like Rust, adapted to what is realistically possible in PHP.

This library does **not** pretend PHP suddenly has algebraic data types or checked exceptions. It simply provides a better failure model than `throw`-and-pray.

---

## Philosophy

> If an error can happen, the caller should have to _look at it_.

Box makes that unavoidable.
