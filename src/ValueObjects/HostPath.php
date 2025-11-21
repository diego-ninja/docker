<?php

// ABOUTME: Represents a validated filesystem path on the host machine.
// ABOUTME: Ensures path exists, is absolute, and is readable.

declare(strict_types=1);

namespace Ninja\Docker\ValueObjects;

final readonly class HostPath
{
    /** @var non-empty-string */
    public private(set) string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Host path cannot be empty');
        }

        if ($value[0] !== '/') {
            throw new \InvalidArgumentException(
                "Host path must be absolute (start with /), got '{$value}'"
            );
        }

        if (!file_exists($value)) {
            throw new \InvalidArgumentException(
                "Host path '{$value}' does not exist"
            );
        }

        if (!is_readable($value)) {
            throw new \InvalidArgumentException(
                "Host path '{$value}' is not readable"
            );
        }

        $this->value = $value;
    }

    public static function from(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
