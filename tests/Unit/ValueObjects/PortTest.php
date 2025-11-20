<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\Port;

it('accepts valid ports', function (int $port) {
    $portVO = Port::from($port);
    expect($portVO->value)->toBe($port);
})->with([1, 80, 443, 8080, 65535]);

it('rejects invalid ports', function (int $invalid) {
    expect(fn() => Port::from($invalid))
        ->toThrow(\InvalidArgumentException::class, 'Port must be between');
})->with([0, -1, 65536, 99999, -999]);

it('converts to string correctly', function () {
    $port = Port::from(8080);
    expect((string)$port)->toBe('8080');
});
