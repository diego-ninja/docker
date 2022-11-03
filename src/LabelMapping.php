<?php

namespace Ninja\Docker;

class LabelMapping
{
    public function __construct(private string $name, private string $value)
    {
    }

    public function __toString()
    {
        return sprintf("-l %s=%s", $this->name, $this->value);
    }
}
