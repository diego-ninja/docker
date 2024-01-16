<?php

namespace Ninja\Docker;

readonly class PortMapping
{
    public function __construct(private int|string $portOnHost, private int $portOnDocker) {}

    public function __toString()
    {
        return sprintf("-p %s:%s", $this->portOnHost, $this->portOnDocker);
    }
}
