<?php

declare(strict_types=1);

namespace Tests;

abstract class TestCase
{
    protected int $assertionsCount = 0;
    protected array $failures = [];

    public function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertionsCount++;
        if (!$condition) {
            $this->failures[] = $message ?: 'Failed asserting that condition is true.';
        }
    }

    public function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($condition) {
            $this->failures[] = $message ?: 'Failed asserting that condition is false.';
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($expected !== $actual) {
            $this->failures[] = $message ?: sprintf("Failed asserting that %s matches expected %s.", var_export($actual, true), var_export($expected, true));
        }
    }

    public function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertionsCount++;
        if (!str_contains($haystack, $needle)) {
            $this->failures[] = $message ?: "Failed asserting that string contains '{$needle}'.";
        }
    }

    public function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertionsCount++;
        if (str_contains($haystack, $needle)) {
            $this->failures[] = $message ?: "Failed asserting that string does not contain '{$needle}'.";
        }
    }

    public function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        $this->assertTrue(!empty($actual), $message ?: 'Failed asserting that value is not empty.');
    }

    public function assertEmpty(mixed $actual, string $message = ''): void
    {
        $this->assertTrue(empty($actual), $message ?: 'Failed asserting that value is empty.');
    }

    public function assertNotNull(mixed $actual, string $message = ''): void
    {
        $this->assertTrue($actual !== null, $message ?: 'Failed asserting that value is not null.');
    }

    public function assertNull(mixed $actual, string $message = ''): void
    {
        $this->assertTrue($actual === null, $message ?: 'Failed asserting that value is null.');
    }

    public function assertFileExists(string $filename, string $message = ''): void
    {
        $this->assertTrue(file_exists($filename), $message ?: "Failed asserting that file '{$filename}' exists.");
    }

    public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContains($needle, $haystack, $message);
    }

    public function getAssertionsCount(): int
    {
        return $this->assertionsCount;
    }

    public function getFailures(): array
    {
        return $this->failures;
    }
}
