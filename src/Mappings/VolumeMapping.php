<?php

// ABOUTME: Represents a volume mount between host filesystem and container.
// ABOUTME: Formats volume mapping for Docker CLI commands.

declare(strict_types=1);

namespace Ninja\Docker\Mappings;

final readonly class VolumeMapping
{
    public function __construct(
        public string $pathOnHost,
        public string $pathOnDocker,
    ) {}

    public function __toString(): string
    {
        return "{$this->pathOnHost}:{$this->pathOnDocker}";
    }
}
