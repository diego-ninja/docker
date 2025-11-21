<?php

// ABOUTME: Represents a validated Docker named volume identifier.
// ABOUTME: Enforces Docker volume naming rules.

declare(strict_types=1);

namespace Ninja\Docker\ValueObjects;

final readonly class VolumeName
{
    /** @var non-empty-string */
    public private(set) string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Volume name cannot be empty');
        }

        if (strlen($value) > 255) {
            throw new \InvalidArgumentException(
                "Volume name cannot exceed 255 characters, got " . strlen($value)
            );
        }

        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $value)) {
            throw new \InvalidArgumentException(
                "Volume name must start with alphanumeric and contain only [a-zA-Z0-9_.-], got '{$value}'"
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
