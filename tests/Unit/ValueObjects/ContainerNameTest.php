<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\ContainerName;

it('validates container names follow Docker rules', function (string $valid) {
    $name = ContainerName::from($valid);
    expect($name->value)->toBe($valid);
})->with([
    'valid-name',
    'container_123',
    'my.container',
    'test-123_abc.xyz',
    'a',
    'container123',
]);

it('rejects invalid container names', function (string $invalid, string $reason) {
    expect(fn() => ContainerName::from($invalid))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    ['INVALID NAME', 'spaces not allowed'],
    ['test@container', '@ not allowed'],
    ['-starts-with-dash', 'must start with alphanumeric'],
    ['', 'empty string'],
    [str_repeat('a', 256), 'too long'],
    ['test;command', 'semicolon not allowed'],
    ['$(injection)', 'special chars not allowed'],
]);

it('converts to string correctly', function () {
    $name = ContainerName::from('test-container');
    expect((string) $name)->toBe('test-container');
});
