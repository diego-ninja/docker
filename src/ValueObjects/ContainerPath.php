<?php

// ABOUTME: Represents a validated absolute path inside a Docker container.
// ABOUTME: No filesystem validation (path is inside container, not host).

declare(strict_types=1);

namespace Ninja\Docker\ValueObjects;

final readonly class ContainerPath
{
    /** @var non-empty-string */
    public private(set) string $value;

    public function __construct(string $value)
    {
        if ($value === '' || $value[0] !== '/') {
            throw new \InvalidArgumentException(
                "Container path must be absolute (start with /), got '{$value}'"
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
