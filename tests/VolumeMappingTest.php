<?php

declare(strict_types=1);

namespace Ninja\Docker\Tests;

use PHPUnit\Framework\TestCase;
use Ninja\Docker\VolumeMapping;

class VolumeMappingTest extends TestCase
{
    /** @test */
    public function it_should_convert_to_a_string_correctly(): void
    {
        $mapping = new VolumeMapping('/foo', '/bar');

        $this->assertEquals('-v /foo:/bar', $mapping);
    }
}
