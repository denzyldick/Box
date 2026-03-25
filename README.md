# Result

`Result` is a modern, type-safe error handling library for PHP 8.1+. It replaces implicit, optional exceptions with an explicit **Result type** that forces callers to acknowledge both success and failure.

Inspired by Rust's `Result` type, this library brings functional error handling and "railway oriented programming" to PHP with full support for **generics**, **immutability**, and **lazy evaluation**.

---

## 🚀 The Core Concept

Instead of throwing exceptions that might be accidentally ignored, your methods return a `Result` object.

```php
/**
 * @return Result<User, UserNotFoundException>
 */
public function findUser(int $id): Result {
    $user = $this->db->find($id);
    
    return Result::when(
        $user !== null,
        $user,
        new UserNotFoundException("User $id not found")
    );
}
```

The caller **must** now explicitly handle the result:

```php
$result = $userRepo->findUser(123);

// Using Pattern Matching
$message = match ($result->state()) {
    ResultState::Ok => "Found user: " . $result->collect()->name,
    ResultState::Error => "Error: " . $result->exception()->getMessage(),
};
```

---

## 🛠️ Feature Highlights

- **Immutable by Design**: Every transformation returns a new `Result` instance.
- **Generic Support**: Fully annotated for PHPStan/Psalm to track your types.
- **Railway Oriented**: Chain operations safely without deeply nested `if` checks.
- **Batch Processing**: Tools to combine or partition groups of results.
- **PHP 8.4 Ready**: Optimized for modern PHP standards.

---

## 📖 Detailed Use Cases

### 1. Creating Results
```php
// Direct creation
$ok = Result::ok("Happy value");
$err = Result::error(new Exception("Sad error"));

// Functional "try" wrapper (captures any thrown Exception)
$res = Result::try(fn() => $this->riskyOperation());

// Conditional creation
$res = Result::when($age >= 18, "Access Granted", new Exception("Too young"));
```

### 2. Functional Transformations (Railway Pattern)
Transform values only if the result is `Ok`. If it's an `Error`, the transformations are skipped automatically.

```php
$nickname = $userRepo->findUser(123)
    ->map(fn(User $u) => $u->name)
    ->map(fn(string $name) => strtolower($name))
    ->unwrapOr("guest");
```

### 3. Handling Collections
Specially designed methods for dealing with results that hold iterables.

```php
$activeUsers = Result::ok($userList)
    ->filterEach(fn(User $u) => $u->isActive)
    ->mapEach(fn(User $u) => $u->email)
    ->collect(); // returns array of emails
```

### 4. Batch Operations
Handle multiple independent results at once.

```php
$results = [$res1, $res2, $res3];

// Fail-fast: Returns Result<array, E> (Ok if all are Ok, else the first Error)
$all = Result::combine($results);

// Collect all: Returns ['ok' => [...], 'error' => [...]]
$partitioned = Result::partition($results);

// First Success: Returns the first Ok, or the first Error if all failed
$any = Result::any($results);
```

### 5. Safe Unwrapping
```php
$val = $res->collect();                // Throws if Error
$val = $res->unwrapOr("default");      // Returns "default" if Error
$val = $res->unwrapOrElse(fn($e) => ...); // Lazy default via closure
$val = $res->expect("User must exist"); // Throws with custom message if Error
```

### 6. Logical Composition
```php
// Combine two independent results into a tuple Result<[A, B], E>
$tuple = $res1->zip($res2);

// Logical AND/OR
$res = $r1->and($r2); // $r2 if $r1 is Ok
$res = $r1->or($r2);  // $r1 if $r1 is Ok, else $r2
```

### 7. Interoperability
```php
// JSON Serialization
echo json_encode(Result::ok("hi")); // {"state":"ok","value":"hi"}

// Iteration (treats Result as a collection of 0 or 1 items)
foreach ($result as $value) {
    echo $value;
}

// Debugging
echo (string) $result; // Result::Ok('Happy value')
```

---

## 🔧 Installation

```bash
composer require denzyl/result
```

---

## 📜 Philosophy

> "If an error can happen, the caller should have to look at it."

This library does not pretend PHP has checked exceptions. It simply provides a better failure model than `throw`-and-pray. It makes your API contracts honest and your code's intent explicit.
