<?php

// ABOUTME: Represents a port mapping between host and container.
// ABOUTME: Validates port numbers via Port value object.

declare(strict_types=1);

namespace Ninja\Docker;

use Ninja\Docker\ValueObjects\Port;

final readonly class PortMapping
{
    public Port $portOnHost;
    public Port $portOnDocker;

    public function __construct(
        Port|int $portOnHost,
        Port|int $portOnDocker
    ) {
        $this->portOnHost   = $portOnHost instanceof Port ? $portOnHost : Port::from($portOnHost);
        $this->portOnDocker = $portOnDocker instanceof Port ? $portOnDocker : Port::from($portOnDocker);
    }

    public function __toString(): string
    {
        return "{$this->portOnHost->value}:{$this->portOnDocker->value}";
    }
}
