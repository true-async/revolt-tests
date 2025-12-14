#!/usr/bin/env php
<?php

class TestRunner {
    private array $tests = [];
    private int $totalPassed = 0;
    private int $totalFailed = 0;
    private float $startTime;

    public function __construct() {
        $this->startTime = microtime(true);
    }

    public function addTest(string $name, string $file): void {
        $this->tests[$name] = $file;
    }

    public function run(): int {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║         Revolt + TrueAsync Integration Tests              ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $failedTests = [];

        foreach ($this->tests as $name => $file) {
            if (!file_exists($file)) {
                echo "⚠ SKIP: $name (file not found: $file)\n\n";
                continue;
            }

            echo "Running: $name\n";
            echo str_repeat("─", 60) . "\n";

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open("php " . escapeshellarg($file), $descriptors, $pipes);

            if (!is_resource($process)) {
                echo "✗ FAIL: Could not start test\n\n";
                $failedTests[] = $name;
                continue;
            }

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            echo $output;

            if (!empty($errors)) {
                echo "\n⚠ Errors/Warnings:\n";
                echo $errors;
            }

            if ($exitCode === 0) {
                echo "✓ Test suite passed\n";
            } else {
                echo "✗ Test suite failed (exit code: $exitCode)\n";
                $failedTests[] = $name;
            }

            echo "\n";
        }

        $elapsed = microtime(true) - $this->startTime;

        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║                      Summary                              ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Total test suites: " . count($this->tests) . "\n";
        echo "Passed: " . (count($this->tests) - count($failedTests)) . "\n";
        echo "Failed: " . count($failedTests) . "\n";
        echo "Time: " . number_format($elapsed, 3) . "s\n";

        if (!empty($failedTests)) {
            echo "\n❌ Failed test suites:\n";
            foreach ($failedTests as $test) {
                echo "  - $test\n";
            }
            echo "\n";
            return 1;
        }

        echo "\n✅ All tests passed!\n\n";
        return 0;
    }
}

$runner = new TestRunner();

// Add all test suites
$runner->addTest("Event Loop", __DIR__ . "/tests/test_event_loop.php");
$runner->addTest("Fiber Integration", __DIR__ . "/tests/test_fiber_integration.php");
$runner->addTest("Stream I/O", __DIR__ . "/tests/test_streams.php");

// Run and exit with appropriate code
exit($runner->run());
