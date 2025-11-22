<?php

// ABOUTME: Represents an environment variable for container.
// ABOUTME: Validates variable name via EnvironmentVariable value object.

declare(strict_types=1);

namespace Ninja\Docker\Mappings;

use Ninja\Docker\ValueObjects\EnvironmentVariable;

final readonly class EnvironmentMapping
{
    public EnvironmentVariable $variable;

    public function __construct(EnvironmentVariable $variable)
    {
        $this->variable = $variable;
    }

    public function __toString(): string
    {
        return (string)$this->variable;
    }
}
