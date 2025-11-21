<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\EnvironmentVariable;

it('accepts valid environment variables', function (string $key, string $value) {
    $env = EnvironmentVariable::from($key, $value);
    expect($env->key)->toBe($key)
        ->and($env->value)->toBe($value)
        ->and((string)$env)->toBe("{$key}={$value}");
})->with([
    ['KEY', 'value'],
    ['DATABASE_URL', 'postgres://localhost'],
    ['_PRIVATE', 'secret'],
    ['ALLOWED_123', 'test'],
]);

it('rejects invalid environment variable keys', function (string $invalidKey) {
    expect(fn() => EnvironmentVariable::from($invalidKey, 'value'))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    '123_STARTS_NUMBER',
    'INVALID-DASH',
    'HAS SPACE',
    '',
    'SPECIAL@CHAR',
]);

it('accepts empty values', function () {
    $env = EnvironmentVariable::from('KEY', '');
    expect($env->value)->toBe('');
});

it('converts to string correctly', function () {
    $env = EnvironmentVariable::from('DATABASE_URL', 'postgres://localhost:5432');
    expect((string)$env)->toBe('DATABASE_URL=postgres://localhost:5432');
});
