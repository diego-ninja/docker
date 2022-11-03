<?php

namespace Ninja\Docker;

class VolumeMapping
{
    public function __construct(private string $pathOnHost, private string $pathOnDocker)
    {
    }

    public function __toString()
    {
        return sprintf("-v %s:%s", $this->pathOnHost, $this->pathOnDocker);
    }
}
