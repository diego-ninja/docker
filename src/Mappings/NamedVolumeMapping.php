<?php

// ABOUTME: Represents a Docker named volume mount.
// ABOUTME: Uses logical volume names instead of host filesystem paths.

declare(strict_types=1);

namespace Ninja\Docker\Mappings;

use Ninja\Docker\ValueObjects\ContainerPath;
use Ninja\Docker\ValueObjects\VolumeName;

final readonly class NamedVolumeMapping
{
    public VolumeName $name;
    public ContainerPath $target;
    public string $flags;

    public function __construct(
        VolumeName|string $name,
        ContainerPath|string $target,
        string $flags = ''
    ) {
        $this->name   = $name instanceof VolumeName ? $name : VolumeName::from($name);
        $this->target = $target instanceof ContainerPath ? $target : ContainerPath::from($target);
        $this->flags  = $flags;
    }

    public function __toString(): string
    {
        $mapping = "{$this->name}:{$this->target}";
        return $this->flags !== '' ? "{$mapping}:{$this->flags}" : $mapping;
    }
}
