<?php

// ABOUTME: Represents a port mapping between host and Docker container.
// ABOUTME: Formats port mapping for Docker CLI commands.

declare(strict_types=1);

namespace Ninja\Docker;

final readonly class PortMapping
{
    public function __construct(
        public int|string $portOnHost,
        public int $portOnDocker,
    ) {}

    public function __toString(): string
    {
        return "-p {$this->portOnHost}:{$this->portOnDocker}";
    }
}
