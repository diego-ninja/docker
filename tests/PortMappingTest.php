<?php

// ABOUTME: Tests for port mapping configuration and string formatting.
// ABOUTME: Validates Docker CLI port flag generation.

declare(strict_types=1);

use Ninja\Docker\PortMapping;
use Ninja\Docker\ValueObjects\Port;

it('should convert to a string correctly', function () {
    $portMapping = new PortMapping(8080, 80);

    expect($portMapping)->toEqual('8080:80');
});

it('accepts Port value objects', function () {
    $mapping = new PortMapping(Port::from(8080), Port::from(80));
    expect($mapping->portOnHost->value)->toBe(8080)
        ->and($mapping->portOnDocker->value)->toBe(80)
        ->and((string)$mapping)->toBe('8080:80');
});

it('accepts primitive integers and converts to Port', function () {
    $mapping = new PortMapping(8080, 80);
    expect($mapping->portOnHost)->toBeInstanceOf(Port::class)
        ->and($mapping->portOnDocker)->toBeInstanceOf(Port::class)
        ->and((string)$mapping)->toBe('8080:80');
});

it('validates ports when primitives provided', function () {
    expect(fn() => new PortMapping(-1, 80))
        ->toThrow(\InvalidArgumentException::class, 'Port must be between');
});

it('converts to string correctly', function () {
    $mapping = new PortMapping(8080, 80);
    expect((string)$mapping)->toBe('8080:80');
});
