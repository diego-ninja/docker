<?php

// ABOUTME: Represents a bind mount from host filesystem to container.
// ABOUTME: Validates host path exists and container path format.

declare(strict_types=1);

namespace Ninja\Docker\Mappings;

use Ninja\Docker\ValueObjects\ContainerPath;
use Ninja\Docker\ValueObjects\HostPath;

final readonly class BindMountMapping
{
    public HostPath $source;
    public ContainerPath $target;
    public string $flags;

    public function __construct(
        HostPath|string $source,
        ContainerPath|string $target,
        string $flags = ''
    ) {
        $this->source = $source instanceof HostPath ? $source : HostPath::from($source);
        $this->target = $target instanceof ContainerPath ? $target : ContainerPath::from($target);
        $this->flags  = $flags;
    }

    public function __toString(): string
    {
        $mapping = "{$this->source}:{$this->target}";
        return $this->flags !== '' ? "{$mapping}:{$this->flags}" : $mapping;
    }
}
