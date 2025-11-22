<?php

// ABOUTME: Tests for named volume mapping using Docker managed volumes.
// ABOUTME: Validates VolumeName and ContainerPath value objects integration.

declare(strict_types=1);

use Ninja\Docker\Mappings\NamedVolumeMapping;
use Ninja\Docker\ValueObjects\ContainerPath;
use Ninja\Docker\ValueObjects\VolumeName;

it('creates named volume with value objects', function () {
    $name   = VolumeName::from('data-volume');
    $target = ContainerPath::from('/app/data');

    $mapping = new NamedVolumeMapping($name, $target);

    expect($mapping->name)->toBe($name)
        ->and($mapping->target)->toBe($target)
        ->and((string)$mapping)->toBe('data-volume:/app/data');
});

it('creates named volume with primitives', function () {
    $mapping = new NamedVolumeMapping('data-volume', '/app/data');

    expect($mapping->name)->toBeInstanceOf(VolumeName::class)
        ->and($mapping->target)->toBeInstanceOf(ContainerPath::class)
        ->and((string)$mapping)->toBe('data-volume:/app/data');
});

it('supports flags', function () {
    $mapping = new NamedVolumeMapping('data-volume', '/app/data', 'ro');

    expect((string)$mapping)->toBe('data-volume:/app/data:ro');
});

it('validates volume name format', function () {
    expect(fn() => new NamedVolumeMapping('-starts-dash', '/app/data'))
        ->toThrow(\InvalidArgumentException::class, 'must start with alphanumeric');
});

it('validates container path format', function () {
    expect(fn() => new NamedVolumeMapping('data-volume', 'relative/path'))
        ->toThrow(\InvalidArgumentException::class, 'must be absolute');
});

it('converts to string correctly', function () {
    $mapping = new NamedVolumeMapping('postgres-data', '/var/lib/postgresql/data');
    expect((string)$mapping)->toBe('postgres-data:/var/lib/postgresql/data');
});

it('handles volume names with dots and underscores', function () {
    $mapping = new NamedVolumeMapping('my_data.vol-123', '/data');
    expect((string)$mapping)->toBe('my_data.vol-123:/data');
});
