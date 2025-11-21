<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\HostPath;

it('accepts existing readable paths', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    $path = HostPath::from($tempFile);
    expect($path->value)->toBe($tempFile);
    unlink($tempFile);
});

it('accepts existing directories', function () {
    $path = HostPath::from(sys_get_temp_dir());
    expect($path->value)->toBe(sys_get_temp_dir());
});

it('rejects non-existent paths', function () {
    expect(fn() => HostPath::from('/nonexistent/path/to/nowhere'))
        ->toThrow(\InvalidArgumentException::class, 'does not exist');
});

it('rejects relative paths', function () {
    expect(fn() => HostPath::from('relative/path'))
        ->toThrow(\InvalidArgumentException::class, 'must be absolute');
});

it('rejects unreadable paths', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    chmod($tempFile, 0000);

    expect(fn() => HostPath::from($tempFile))
        ->toThrow(\InvalidArgumentException::class, 'not readable');

    chmod($tempFile, 0644);
    unlink($tempFile);
});

it('converts to string correctly', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    $path = HostPath::from($tempFile);
    expect((string) $path)->toBe($tempFile);
    unlink($tempFile);
});
