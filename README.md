# Revolt + TrueAsync Integration Tests

This project tests the integration between [Revolt Event Loop](https://revolt.run) and TrueAsync (PHP's async extension with fiber support).

## Overview

Revolt is a rock-solid event loop for concurrent PHP applications that leverages PHP 8.1's native Fiber implementation. TrueAsync is an extension that provides async/await functionality and coroutine support for PHP.

This test suite ensures that Revolt works correctly with TrueAsync's fiber handling, garbage collection, and coroutine management.

## Requirements

- PHP 8.6+ with TrueAsync extension enabled (fiber-support)
- Composer

## Installation

```bash
composer install
```

## Running Tests

Run all test suites with a single command:

```bash
php run_tests.php
```

Or run individual test suites:

```bash
php tests/test_event_loop.php
php tests/test_fiber_integration.php
php tests/test_streams.php
```

## Test Suites

### 1. Event Loop Tests (`tests/test_event_loop.php`)

Tests core event loop functionality:
- **Defer execution order** - Validates that deferred callbacks execute in FIFO order
- **Delay timing** - Verifies accurate timing of delayed callbacks
- **Repeat with cancellation** - Tests repeating timers and proper cancellation
- **Callback cancellation** - Ensures cancelled callbacks don't execute
- **Multiple defer batches** - Tests nested defers and execution order

### 2. Fiber Integration Tests (`tests/test_fiber_integration.php`)

Tests Revolt's integration with PHP Fibers:
- **Fiber suspend/resume** - Basic fiber lifecycle within event loop
- **Nested fibers** - Fibers created within other fibers
- **Fiber with delayed resume** - Combining fibers with event loop timers
- **Multiple fibers scheduling** - Concurrent fiber execution
- **Exception in fiber** - Proper exception handling in fibers

### 3. Stream I/O Tests (`tests/test_streams.php`)

Tests stream handling in the event loop:
- **Stream readable** - Reading from streams asynchronously
- **Stream writable** - Writing to streams asynchronously
- **Stream callback cancellation** - Cancelling stream watchers
- **Pipe between streams** - Piping data between multiple streams

## Key Issues Tested

This test suite specifically validates fixes for:

1. **Fiber GC during start**: Ensures that garbage collection during `fiber->start()` doesn't cause assertion failures in TrueAsync
2. **Suspended fiber cleanup**: Validates that suspended fibers are properly cleaned up without triggering deadlock detection
3. **Multiple fiber lifecycle**: Tests creation, suspension, resumption, and destruction of multiple fibers

## Test Results

All tests should pass with exit code 0:

```
✅ All tests passed!
```

## Exit Codes

- `0` - All tests passed
- `1` - One or more tests failed

## Implementation Notes

### Arrow Functions vs Closures

Revolt requires all callbacks to return `null`. Arrow functions (`fn()`) always return the result of their expression, which violates this requirement. Therefore, all callbacks use regular closures:

```php
// ❌ Wrong - arrow function returns a value
EventLoop::defer(fn() => $value = 'test');

// ✅ Correct - closure returns null
EventLoop::defer(function() use (&$value) { $value = 'test'; });
```

### Non-blocking Streams

Stream tests use non-blocking mode (`stream_set_blocking($stream, false)`) to work properly with the event loop.

## Contributing

When adding new tests:
1. Create a new test class extending the test pattern
2. Add the test to `run_tests.php`
3. Ensure all callbacks return `null`
4. Use `$this->assert()` for test assertions

## License

This is a test project for validating Revolt + TrueAsync integration.
