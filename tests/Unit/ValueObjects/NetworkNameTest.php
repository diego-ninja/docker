<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\NetworkName;

it('validates network names follow Docker rules', function (string $valid) {
    $name = NetworkName::from($valid);
    expect($name->value)->toBe($valid);
})->with([
    'my-network',
    'network_123',
    'bridge',
    'host',
]);

it('rejects invalid network names', function (string $invalid) {
    expect(fn() => NetworkName::from($invalid))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    'INVALID NETWORK',
    'net@work',
    '',
    str_repeat('n', 256),
]);

it('converts to string correctly', function () {
    $network = NetworkName::from('my-network');
    expect((string)$network)->toBe('my-network');
});
