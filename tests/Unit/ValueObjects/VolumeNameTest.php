<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\VolumeName;

it('validates volume names', function (string $valid) {
    $name = VolumeName::from($valid);
    expect($name->value)->toBe($valid);
})->with([
    'my-volume',
    'data_vol',
    'vol.123',
    'postgres-data',
]);

it('rejects invalid volume names', function (string $invalid) {
    expect(fn() => VolumeName::from($invalid))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    '',
    'vol with spaces',
    '-starts-dash',
    str_repeat('v', 256),
]);

it('converts to string correctly', function () {
    $volume = VolumeName::from('data-volume');
    expect((string) $volume)->toBe('data-volume');
});
