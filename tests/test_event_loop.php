<?php

require __DIR__ . '/../vendor/autoload.php';

use Revolt\EventLoop;

class EventLoopTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        echo "=== Event Loop Tests ===\n\n";

        $this->testDeferExecutionOrder();
        $this->testDelayTiming();
        $this->testRepeatCancellation();
        $this->testCallbackCancellation();
        $this->testMultipleDefers();

        echo "\nResults: {$this->passed} passed, {$this->failed} failed\n";
        return $this->failed > 0 ? 1 : 0;
    }

    private function testDeferExecutionOrder(): void {
        echo "Test: Defer execution order... ";
        $order = [];

        EventLoop::defer(function() use (&$order) { $order[] = 'first'; });
        EventLoop::defer(function() use (&$order) { $order[] = 'second'; });
        EventLoop::defer(function() use (&$order) { $order[] = 'third'; });

        $order[] = 'before';
        EventLoop::run();
        $order[] = 'after';

        $expected = ['before', 'first', 'second', 'third', 'after'];
        $this->assert($order === $expected, "Expected order, got: " . json_encode($order));
    }

    private function testDelayTiming(): void {
        echo "Test: Delay timing... ";
        $start = microtime(true);
        $executed = false;

        EventLoop::delay(0.1, function() use (&$executed) {
            $executed = true;
        });

        EventLoop::run();
        $elapsed = microtime(true) - $start;

        $this->assert($executed === true, "Delay callback not executed");
        $this->assert($elapsed >= 0.1 && $elapsed < 0.2, "Timing off: {$elapsed}s");
    }

    private function testRepeatCancellation(): void {
        echo "Test: Repeat with cancellation... ";
        $count = 0;

        $id = EventLoop::repeat(0.05, function($callbackId) use (&$count) {
            $count++;
            if ($count >= 3) {
                EventLoop::cancel($callbackId);
            }
        });

        EventLoop::run();

        $this->assert($count === 3, "Expected 3 executions, got {$count}");
    }

    private function testCallbackCancellation(): void {
        echo "Test: Callback cancellation... ";
        $executed = false;

        $id = EventLoop::defer(function() use (&$executed) { $executed = true; });
        EventLoop::cancel($id);
        EventLoop::run();

        $this->assert($executed === false, "Cancelled callback should not execute");
    }

    private function testMultipleDefers(): void {
        echo "Test: Multiple defer batches... ";
        $results = [];

        EventLoop::defer(function() use (&$results) {
            $results[] = 'batch1-a';
            EventLoop::defer(function() use (&$results) { $results[] = 'batch2-a'; });
        });

        EventLoop::defer(function() use (&$results) {
            $results[] = 'batch1-b';
            EventLoop::defer(function() use (&$results) { $results[] = 'batch2-b'; });
        });

        EventLoop::run();

        $this->assert(count($results) === 4, "Expected 4 callbacks, got " . count($results));
        $this->assert($results[0] === 'batch1-a' && $results[1] === 'batch1-b', "Wrong order");
    }

    private function assert(bool $condition, string $message): void {
        if ($condition) {
            echo "PASS\n";
            $this->passed++;
        } else {
            echo "FAIL: $message\n";
            $this->failed++;
        }
    }
}

$test = new EventLoopTest();
exit($test->run());
