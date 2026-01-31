# PHP 7.4 to PHP 7.1 Porting Guide

This document lists all features introduced in PHP 7.2, 7.3, and 7.4 that are incompatible with PHP 7.1, along with detection patterns and substitution strategies.

---

## PHP 7.4 Features (Released: Nov 2019)

### 1. Typed Properties

**What it is:** Class properties can have type declarations.

```php
// PHP 7.4+
class User {
    public int $id;
    public string $name;
    private ?array $data = null;
}
```

**How to find:**
```bash
# Grep pattern - property declarations with types
grep -rPn '(public|private|protected)\s+(int|string|float|bool|array|object|iterable|\??\w+)\s+\$' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1
class User {
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var array|null */
    private $data = null;
}
```

---

### 2. Arrow Functions (Short Closures)

**What it is:** Concise anonymous functions with implicit `use` binding.

```php
// PHP 7.4+
$factor = 10;
$multiply = fn($n) => $n * $factor;
$nums = array_map(fn($n) => $n * 2, [1, 2, 3]);
```

**How to find:**
```bash
# Grep pattern - fn keyword followed by parentheses
grep -rPn '\bfn\s*\(' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1
$factor = 10;
$multiply = function($n) use ($factor) { return $n * $factor; };
$nums = array_map(function($n) { return $n * 2; }, [1, 2, 3]);
```

---

### 3. Null Coalescing Assignment Operator (`??=`)

**What it is:** Assigns a value only if the variable is null or undefined.

```php
// PHP 7.4+
$array['key'] ??= 'default';
$this->data ??= [];
```

**How to find:**
```bash
# Grep pattern - ??= operator
grep -rn '\?\?=' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1
$array['key'] = $array['key'] ?? 'default';
$this->data = $this->data ?? [];
// Or using isset:
if (!isset($array['key'])) {
    $array['key'] = 'default';
}
```

---

### 4. Spread Operator in Arrays

**What it is:** Unpack arrays inside array literals.

```php
// PHP 7.4+
$parts = ['apple', 'pear'];
$fruits = ['banana', ...$parts, 'watermelon'];
```

**How to find:**
```bash
# Grep pattern - spread operator in array context
grep -rPn '\[\s*[^]]*\.\.\.\$' src/
grep -rn '\.\.\.\\$' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1
$parts = ['apple', 'pear'];
$fruits = array_merge(['banana'], $parts, ['watermelon']);
```

---

### 5. Numeric Literal Separator

**What it is:** Underscores in numeric literals for readability.

```php
// PHP 7.4+
$million = 1_000_000;
$hex = 0xCAFE_F00D;
$binary = 0b0101_1111;
```

**How to find:**
```bash
# Grep pattern - numbers with underscores
grep -rPn '\b\d+_\d+' src/
grep -rPn '0x[0-9a-fA-F_]+' src/
grep -rPn '0b[01_]+' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1 - simply remove underscores
$million = 1000000;
$hex = 0xCAFEF00D;
$binary = 0b01011111;
```

---

### 6. Covariant Return Types / Contravariant Parameters

**What it is:** Child classes can return more specific types or accept more general parameters.

```php
// PHP 7.4+
class ParentClass {
    public function create(): ParentClass { }
}
class ChildClass extends ParentClass {
    public function create(): ChildClass { }  // More specific return
}
```

**How to find:** Manual review of class hierarchies with return types.

**PHP 7.1 equivalent:** Use the parent type or remove return type declaration.

---

## PHP 7.3 Features (Released: Dec 2018)

### 1. Flexible Heredoc/Nowdoc Syntax

**What it is:** Closing marker can be indented; indentation is stripped from content.

```php
// PHP 7.3+
$html = <<<HTML
    <div>
        Content
    </div>
    HTML;  // Indented closing marker
```

**How to find:**
```bash
# Look for heredoc with indented closing marker
grep -rPnz '<<<\s*(\w+).*?\n.*?^\s+\1;' src/
# Or manually search for heredoc patterns and check closing markers
grep -rn "<<<" src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1 - closing marker must be at column 0
$html = <<<HTML
    <div>
        Content
    </div>
HTML;
```

---

### 2. Trailing Commas in Function/Method Calls

**What it is:** Allows trailing comma after last argument in function calls.

```php
// PHP 7.3+
someFunction(
    $arg1,
    $arg2,
    $arg3,  // Trailing comma allowed
);
```

**How to find:**
```bash
# Grep pattern - comma followed by closing parenthesis (with possible whitespace/newline)
grep -rPn ',\s*\)' src/
```

**PHP 7.1 equivalent:** Remove the trailing comma.

---

### 3. `array_key_first()` and `array_key_last()`

**What it is:** Get first/last key of an array without modifying pointer.

```php
// PHP 7.3+
$first = array_key_first($array);
$last = array_key_last($array);
```

**How to find:**
```bash
grep -rn 'array_key_first\|array_key_last' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1
$first = key($array);  // Only if pointer is at start
// Or more robust:
$keys = array_keys($array);
$first = reset($keys);
$last = end($keys);

// Or define polyfill functions:
if (!function_exists('array_key_first')) {
    function array_key_first(array $arr) {
        foreach ($arr as $key => $unused) {
            return $key;
        }
        return null;
    }
}
if (!function_exists('array_key_last')) {
    function array_key_last(array $arr) {
        if (empty($arr)) {
            return null;
        }
        return array_keys($arr)[count($arr) - 1];
    }
}
```

---

### 4. `is_countable()` Function

**What it is:** Check if a value is countable (array or Countable object).

```php
// PHP 7.3+
if (is_countable($value)) {
    $count = count($value);
}
```

**How to find:**
```bash
grep -rn 'is_countable' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1
if (is_array($value) || $value instanceof \Countable) {
    $count = count($value);
}

// Or define polyfill:
if (!function_exists('is_countable')) {
    function is_countable($value): bool {
        return is_array($value) || $value instanceof \Countable;
    }
}
```

---

### 5. `JSON_THROW_ON_ERROR` Constant

**What it is:** Makes `json_encode()`/`json_decode()` throw exceptions on error.

```php
// PHP 7.3+
$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
```

**How to find:**
```bash
grep -rn 'JSON_THROW_ON_ERROR' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1
$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \JsonException(json_last_error_msg(), json_last_error());
}
```

---

### 6. Reference Assignments in Array Destructuring

**What it is:** Use references in list() or [] destructuring.

```php
// PHP 7.3+
[&$a, &$b] = $array;
list(&$x, &$y) = $data;
```

**How to find:**
```bash
grep -rPn '\[.*&\$.*\]\s*=' src/
grep -rPn 'list\s*\(.*&\$' src/
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1 - assign references manually
$a = &$array[0];
$b = &$array[1];
```

---

## PHP 7.2 Features (Released: Nov 2017)

### 1. `object` Type Hint

**What it is:** New `object` pseudo-type for parameters and return types.

```php
// PHP 7.2+
function processObject(object $obj): object {
    return $obj;
}
```

**How to find:**
```bash
# As parameter type
grep -rPn 'function\s+\w+\s*\([^)]*\bobject\b' src/
# As return type
grep -rPn '\):\s*\??object\b' src/
# Both
grep -rn '\bobject\b' src/ | grep -E '(function|\):)'
```

**PHP 7.1 equivalent:**
```php
// PHP 7.1 - remove type hint or use PHPDoc
/**
 * @param object $obj
 * @return object
 */
function processObject($obj) {
    return $obj;
}
```

---

### 2. Parameter Type Widening

**What it is:** Child classes can omit parameter types declared in parent.

```php
// PHP 7.2+
interface A {
    public function test(array $input);
}
class B implements A {
    public function test($input) {}  // Type omitted - widened
}
```

**Note:** This is a PHP 7.2+ feature. In PHP 7.1, you must keep the same type signature.

---

### 3. Trailing Comma in Grouped Namespaces

**What it is:** Trailing comma in grouped use statements.

```php
// PHP 7.2+
use Foo\Bar\{
    Foo,
    Bar,
    Baz,  // Trailing comma
};
```

**How to find:**
```bash
grep -rPnz 'use\s+[^;]+\{[^}]*,\s*\}' src/
```

**PHP 7.1 equivalent:** Remove the trailing comma.

---

## Comprehensive Search Commands

Run these commands from the project root to find all potential incompatibilities:

```bash
# === PHP 7.4 Features ===

# Typed properties
grep -rPn '(public|private|protected)\s+(int|string|float|bool|array|object|iterable|self|parent|\??\w+)\s+\$\w+' src/

# Arrow functions
grep -rPn '\bfn\s*\(' src/

# Null coalescing assignment
grep -rn '\?\?=' src/

# Spread in arrays
grep -rPn '\[.*\.\.\.\$' src/

# Numeric separators
grep -rPn '\d+_\d+' src/

# === PHP 7.3 Features ===

# array_key_first / array_key_last
grep -rn 'array_key_first\|array_key_last' src/

# is_countable
grep -rn 'is_countable' src/

# JSON_THROW_ON_ERROR
grep -rn 'JSON_THROW_ON_ERROR' src/

# Trailing commas in function calls (may have false positives)
grep -rPn ',\s*\)' src/

# Reference in array destructuring
grep -rPn '\[.*&\$.*\]\s*=' src/

# === PHP 7.2 Features ===

# object type hint
grep -rPn '\bobject\b' src/ | grep -v '//' | grep -E '(function|\):)'
```

---

## Polyfill Library

Consider using `symfony/polyfill-php73` and `symfony/polyfill-php72` packages:

```bash
composer require symfony/polyfill-php72 symfony/polyfill-php73
```

These provide polyfills for:
- `array_key_first()`, `array_key_last()`
- `is_countable()`
- `JsonException` class
- And more

---

## Current Codebase Status

Based on exploration, this codebase has **already been modified for PHP 7.1 compatibility** (per recent commits):
- `84811db` - fix setcookie
- `6854cd5` - controller update for compatibility
- `1453a91` - remove return types for compatibility
- `e61bdc4` - support for lower php versions

No PHP 7.2, 7.3, or 7.4 specific features were found in the `src/` directory.

---

## Testing Compatibility

After making changes, test with PHP 7.1:

```bash
# Using Docker
docker run --rm -v $(pwd):/app -w /app php:7.1-cli php -l src/path/to/file.php

# Lint all PHP files
find src/ -name "*.php" -exec php -l {} \;

# Run tests
composer test:unit
```

## References

- [PHP 7.4 Migration Guide](https://www.php.net/manual/en/migration74.php)
- [PHP 7.3 Migration Guide](https://www.php.net/manual/en/migration73.php)
- [PHP 7.2 Migration Guide](https://www.php.net/manual/en/migration72.php)
