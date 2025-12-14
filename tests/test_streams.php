<?php

require __DIR__ . '/../vendor/autoload.php';

use Revolt\EventLoop;

class StreamsTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        echo "=== Stream I/O Tests ===\n\n";

        $this->testStreamReadable();
        $this->testStreamWritable();
        $this->testStreamCancellation();
        $this->testPipeStreams();

        echo "\nResults: {$this->passed} passed, {$this->failed} failed\n";
        return $this->failed > 0 ? 1 : 0;
    }

    private function testStreamReadable(): void {
        echo "Test: Stream readable... ";

        [$reader, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_blocking($reader, false);
        stream_set_blocking($writer, false);

        $received = '';

        EventLoop::onReadable($reader, function($callbackId, $stream) use (&$received) {
            $data = fread($stream, 1024);
            if ($data !== false && $data !== '') {
                $received .= $data;
            }
            EventLoop::cancel($callbackId);
        });

        EventLoop::defer(function() use ($writer) { fwrite($writer, "Hello"); });

        EventLoop::run();

        fclose($reader);
        fclose($writer);

        $this->assert($received === "Hello", "Expected 'Hello', got '$received'");
    }

    private function testStreamWritable(): void {
        echo "Test: Stream writable... ";

        [$reader, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_blocking($reader, false);
        stream_set_blocking($writer, false);

        $written = false;

        EventLoop::onWritable($writer, function($callbackId, $stream) use (&$written) {
            fwrite($stream, "Test");
            $written = true;
            EventLoop::cancel($callbackId);
        });

        EventLoop::run();

        $received = fread($reader, 1024);

        fclose($reader);
        fclose($writer);

        $this->assert($written === true, "Stream not written");
        $this->assert($received === "Test", "Expected 'Test', got '$received'");
    }

    private function testStreamCancellation(): void {
        echo "Test: Stream callback cancellation... ";

        [$reader, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_blocking($reader, false);

        $executed = false;
        $timerExecuted = false;

        $id = EventLoop::onReadable($reader, function() use (&$executed) {
            $executed = true;
        });

        EventLoop::cancel($id);

        // Write data to make stream readable
        fwrite($writer, "Data");

        // Add a timer to ensure loop runs and checks streams
        EventLoop::delay(0.05, function() use (&$timerExecuted) {
            $timerExecuted = true;
        });

        EventLoop::run();

        fclose($reader);
        fclose($writer);

        $this->assert($timerExecuted === true, "Timer should execute");
        $this->assert($executed === false, "Cancelled stream callback should not execute");
    }

    private function testPipeStreams(): void {
        echo "Test: Pipe between streams... ";

        [$r1, $w1] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        [$r2, $w2] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        stream_set_blocking($r1, false);
        stream_set_blocking($w1, false);
        stream_set_blocking($r2, false);
        stream_set_blocking($w2, false);

        $finalData = '';

        // Pipe: r1 -> w2
        EventLoop::onReadable($r1, function($id, $stream) use ($w2) {
            $data = fread($stream, 1024);
            if ($data !== false && $data !== '') {
                fwrite($w2, $data);
            }
            EventLoop::cancel($id);
        });

        // Read from r2
        EventLoop::onReadable($r2, function($id, $stream) use (&$finalData) {
            $data = fread($stream, 1024);
            if ($data !== false && $data !== '') {
                $finalData .= $data;
            }
            EventLoop::cancel($id);
        });

        // Write to w1
        EventLoop::defer(function() use ($w1) { fwrite($w1, "Piped"); });

        EventLoop::run();

        fclose($r1); fclose($w1);
        fclose($r2); fclose($w2);

        $this->assert($finalData === "Piped", "Expected 'Piped', got '$finalData'");
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

$test = new StreamsTest();
exit($test->run());
