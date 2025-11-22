<?php

// ABOUTME: Tests for bind mount mapping from host filesystem to container.
// ABOUTME: Validates HostPath and ContainerPath value objects integration.

declare(strict_types=1);

use Ninja\Docker\Mappings\BindMountMapping;
use Ninja\Docker\ValueObjects\ContainerPath;
use Ninja\Docker\ValueObjects\HostPath;

it('creates bind mount with value objects', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    $source   = HostPath::from($tempFile);
    $target   = ContainerPath::from('/app/data');

    $mapping = new BindMountMapping($source, $target);

    expect($mapping->source)->toBe($source)
        ->and($mapping->target)->toBe($target)
        ->and((string)$mapping)->toBe("{$tempFile}:/app/data");

    unlink($tempFile);
});

it('creates bind mount with primitives', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');

    $mapping = new BindMountMapping($tempFile, '/app/data');

    expect($mapping->source)->toBeInstanceOf(HostPath::class)
        ->and($mapping->target)->toBeInstanceOf(ContainerPath::class)
        ->and((string)$mapping)->toBe("{$tempFile}:/app/data");

    unlink($tempFile);
});

it('supports flags', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');

    $mapping = new BindMountMapping($tempFile, '/app/data', 'ro');

    expect((string)$mapping)->toBe("{$tempFile}:/app/data:ro");

    unlink($tempFile);
});

it('validates host path exists', function () {
    expect(fn() => new BindMountMapping('/nonexistent/path', '/app/data'))
        ->toThrow(\InvalidArgumentException::class, 'does not exist');
});

it('validates container path format', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');

    expect(fn() => new BindMountMapping($tempFile, 'relative/path'))
        ->toThrow(\InvalidArgumentException::class, 'must be absolute');

    unlink($tempFile);
});

it('converts to string correctly', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');

    $mapping = new BindMountMapping($tempFile, '/app/data');
    expect((string)$mapping)->toBe("{$tempFile}:/app/data");

    unlink($tempFile);
});
