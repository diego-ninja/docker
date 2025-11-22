<?php

// ABOUTME: Represents a validated network port number (1-65535).
// ABOUTME: Immutable value object with asymmetric visibility for type safety.

declare(strict_types=1);

namespace Ninja\Docker\ValueObjects;

final readonly class Port
{
    /** @var positive-int */
    public private(set) int $value;

    public function __construct(int $value)
    {
        if ($value < 1 || $value > 65535) {
            throw new \InvalidArgumentException(
                "Port must be between 1 and 65535, got {$value}"
            );
        }
        $this->value = $value;
    }

    public static function from(int $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
