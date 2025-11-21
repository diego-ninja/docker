<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\ContainerPath;

it('accepts absolute container paths', function (string $valid) {
    $path = ContainerPath::from($valid);
    expect($path->value)->toBe($valid);
})->with([
    '/app',
    '/data',
    '/var/www',
    '/root/.ssh/authorized_keys',
]);

it('rejects non-absolute paths', function (string $invalid) {
    expect(fn() => ContainerPath::from($invalid))
        ->toThrow(\InvalidArgumentException::class, 'must be absolute');
})->with([
    'relative/path',
    './current/dir',
    '../parent',
    '',
]);

it('converts to string correctly', function () {
    $path = ContainerPath::from('/app/data');
    expect((string) $path)->toBe('/app/data');
});
