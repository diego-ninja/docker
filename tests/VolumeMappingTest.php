<?php

// ABOUTME: Tests for volume mapping configuration and string formatting.
// ABOUTME: Validates Docker CLI volume flag generation.

declare(strict_types=1);

use Ninja\Docker\Mappings\VolumeMapping;

it('should convert to a string correctly', function () {
    $mapping = new VolumeMapping('/foo', '/bar');

    expect($mapping)->toEqual('/foo:/bar');
});
