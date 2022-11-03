<?php

declare(strict_types=1);

namespace Ninja\Docker\Tests;

use PHPUnit\Framework\TestCase;
use Ninja\Docker\PortMapping;

class PortMappingTest extends TestCase
{
    /** @test */
    public function it_should_convert_to_a_string_correctly()
    {
        $portMapping = new PortMapping(8080, 80);

        $this->assertEquals('-p 8080:80', $portMapping);
    }
}
