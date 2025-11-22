<?php

// ABOUTME: Represents a validated environment variable key-value pair.
// ABOUTME: Enforces POSIX environment variable naming rules for keys.

declare(strict_types=1);

namespace Ninja\Docker\ValueObjects;

final readonly class EnvironmentVariable
{
    /** @var non-empty-string */
    public private(set) string $key;

    public private(set) string $value;

    public function __construct(string $key, string $value)
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Environment variable key cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException(
                "Environment variable key must start with letter/underscore and contain only [a-zA-Z0-9_], got '{$key}'"
            );
        }

        $this->key   = $key;
        $this->value = $value;
    }

    public static function from(string $key, string $value): self
    {
        return new self($key, $value);
    }

    public function __toString(): string
    {
        return "{$this->key}={$this->value}";
    }
}
