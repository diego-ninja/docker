<?php

// ABOUTME: Represents a label key-value pair for container metadata.
// ABOUTME: Formats label for Docker CLI commands.

declare(strict_types=1);

namespace Ninja\Docker;

final readonly class LabelMapping
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}

    public function __toString(): string
    {
        return "-l {$this->name}={$this->value}";
    }
}
