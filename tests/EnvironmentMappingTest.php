<?php

// ABOUTME: Tests for environment variable mapping and string formatting.
// ABOUTME: Validates Docker CLI environment flag generation.

declare(strict_types=1);

use Ninja\Docker\Mappings\EnvironmentMapping;
use Ninja\Docker\ValueObjects\EnvironmentVariable;

it('should convert to a string correctly', function () {
    $env     = EnvironmentVariable::from('APP_URL', 'http://localhost');
    $mapping = new EnvironmentMapping($env);

    expect($mapping)->toEqual('APP_URL=http://localhost');
});

it('accepts EnvironmentVariable value object', function () {
    $env     = EnvironmentVariable::from('DATABASE_URL', 'postgres://localhost');
    $mapping = new EnvironmentMapping($env);

    expect($mapping->variable)->toBe($env)
        ->and((string)$mapping)->toBe('DATABASE_URL=postgres://localhost');
});

it('converts to string correctly', function () {
    $env     = EnvironmentVariable::from('API_KEY', 'secret123');
    $mapping = new EnvironmentMapping($env);

    expect((string)$mapping)->toBe('API_KEY=secret123');
});

it('handles empty values', function () {
    $env     = EnvironmentVariable::from('EMPTY_VAR', '');
    $mapping = new EnvironmentMapping($env);

    expect((string)$mapping)->toBe('EMPTY_VAR=');
});

it('validates environment variable key via VO', function () {
    expect(fn() => new EnvironmentMapping(EnvironmentVariable::from('INVALID-KEY', 'value')))
        ->toThrow(\InvalidArgumentException::class, 'must start with letter');
});
