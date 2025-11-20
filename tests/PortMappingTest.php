<?php

// ABOUTME: Tests for port mapping configuration and string formatting.
// ABOUTME: Validates Docker CLI port flag generation.

declare(strict_types=1);

use Ninja\Docker\PortMapping;

it('should convert to a string correctly', function () {
    $portMapping = new PortMapping(8080, 80);

    expect($portMapping)->toEqual('-p 8080:80');
});
