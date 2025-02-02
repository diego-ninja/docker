<?php

namespace Ninja\Docker;

readonly class EnvironmentMapping
{
    public function __construct(private string $name, private string $value) {}

    public function __toString()
    {
        return sprintf("-e %s=%s", $this->name, $this->value);
    }
}
