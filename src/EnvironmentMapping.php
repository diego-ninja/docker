<?php

// ABOUTME: Represents an environment variable mapping for container configuration.
// ABOUTME: Formats environment variable for Docker CLI commands.

declare(strict_types=1);

namespace Ninja\Docker;

final readonly class EnvironmentMapping
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}

    public function __toString(): string
    {
        return "-e {$this->name}={$this->value}";
    }
}
