<?php

require __DIR__ . '/../vendor/autoload.php';

use Revolt\EventLoop;

class FiberIntegrationTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        echo "=== Fiber Integration Tests ===\n\n";

        $this->testFiberSuspendResume();
        $this->testNestedFibers();
        $this->testFiberWithDelay();
        $this->testMultipleFibersScheduling();
        $this->testFiberException();

        echo "\nResults: {$this->passed} passed, {$this->failed} failed\n";
        return $this->failed > 0 ? 1 : 0;
    }

    private function testFiberSuspendResume(): void {
        echo "Test: Fiber suspend/resume... ";
        $steps = [];

        EventLoop::defer(function() use (&$steps) {
            $fiber = new Fiber(function() use (&$steps) {
                $steps[] = 'fiber-start';
                Fiber::suspend();
                $steps[] = 'fiber-resume';
                return 'done';
            });

            $steps[] = 'before-start';
            $fiber->start();
            $steps[] = 'after-start';

            EventLoop::defer(function() use ($fiber, &$steps) {
                $result = $fiber->resume();
                $steps[] = "result-$result";
            });
        });

        EventLoop::run();

        $expected = ['before-start', 'fiber-start', 'after-start', 'fiber-resume', 'result-done'];
        $this->assert($steps === $expected, "Order mismatch: " . json_encode($steps));
    }

    private function testNestedFibers(): void {
        echo "Test: Nested fibers... ";
        $results = [];

        EventLoop::defer(function() use (&$results) {
            $outer = new Fiber(function() use (&$results) {
                $results[] = 'outer-start';

                $inner = new Fiber(function() use (&$results) {
                    $results[] = 'inner-start';
                    Fiber::suspend();
                    $results[] = 'inner-end';
                    return 'inner-value';
                });

                $inner->start();
                Fiber::suspend();

                $innerResult = $inner->resume();
                $results[] = "inner-$innerResult";

                return 'outer-value';
            });

            $outer->start();
            EventLoop::defer(function() use ($outer, &$results) {
                $results[] = 'outer-' . $outer->resume();
            });
        });

        EventLoop::run();

        $this->assert(in_array('inner-start', $results), "Inner fiber didn't start");
        $this->assert(in_array('inner-inner-value', $results), "Inner result not received");
        $this->assert(in_array('outer-outer-value', $results), "Outer result not received");
    }

    private function testFiberWithDelay(): void {
        echo "Test: Fiber with delayed resume... ";
        $completed = false;

        EventLoop::defer(function() use (&$completed) {
            $fiber = new Fiber(function() use (&$completed) {
                Fiber::suspend();
                $completed = true;
            });

            $fiber->start();

            // Resume after delay
            EventLoop::delay(0.05, function() use ($fiber) { $fiber->resume(); });
        });

        EventLoop::run();

        $this->assert($completed === true, "Fiber not resumed after delay");
    }

    private function testMultipleFibersScheduling(): void {
        echo "Test: Multiple fibers scheduling... ";
        $order = [];

        for ($i = 0; $i < 3; $i++) {
            EventLoop::defer(function() use ($i, &$order) {
                $fiber = new Fiber(function() use ($i, &$order) {
                    $order[] = "fiber-$i-start";
                    Fiber::suspend();
                    $order[] = "fiber-$i-end";
                });

                $fiber->start();
                EventLoop::defer(function() use ($fiber) { $fiber->resume(); });
            });
        }

        EventLoop::run();

        $this->assert(count($order) === 6, "Expected 6 events, got " . count($order));
        $this->assert($order[0] === 'fiber-0-start', "Wrong first event: {$order[0]}");
    }

    private function testFiberException(): void {
        echo "Test: Exception in fiber... ";
        $caught = false;

        EventLoop::defer(function() use (&$caught) {
            $fiber = new Fiber(function() {
                throw new RuntimeException("Test error");
            });

            try {
                $fiber->start();
            } catch (RuntimeException $e) {
                $caught = $e->getMessage() === "Test error";
            }
        });

        EventLoop::run();

        $this->assert($caught === true, "Exception not caught properly");
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

$test = new FiberIntegrationTest();
exit($test->run());
