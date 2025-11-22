<?php

// ABOUTME: Tests for Docker facade shortcuts and service registration.
// ABOUTME: Validates static shortcuts, registry, and config options.

declare(strict_types=1);

use Ninja\Docker\Docker;
use Ninja\Docker\DockerContainer;

it('provides nginx shortcut', function () {
    $container = Docker::nginx();

    expect($container)->toBeInstanceOf(DockerContainer::class);
});

it('creates nginx with default port mapping', function () {
    $container = Docker::nginx();

    expect($container->portMappings)->toHaveCount(1);

    /** @var \Ninja\Docker\PortMapping $portMapping */
    $portMapping = $container->portMappings[0];
    expect($portMapping->portOnHost->value)->toBe(80)
        ->and($portMapping->portOnDocker->value)->toBe(80);
});

it('creates nginx with default name prefix', function () {
    $container = Docker::nginx();

    expect($container->name)->not->toBeNull()
        ->and($container->name?->value)->toStartWith('nginx-');
});

it('provides mysql shortcut', function () {
    $container = Docker::mysql();

    expect($container)->toBeInstanceOf(DockerContainer::class)
        ->and($container->image->value)->toBe('mysql:8')
        ->and($container->portMappings)->toHaveCount(1)
        ->and($container->name?->value)->toStartWith('mysql-');
});

it('provides postgres shortcut', function () {
    $container = Docker::postgres();

    expect($container)->toBeInstanceOf(DockerContainer::class)
        ->and($container->image->value)->toBe('postgres:16')
        ->and($container->portMappings)->toHaveCount(1)
        ->and($container->name?->value)->toStartWith('postgres-');
});

it('provides redis shortcut', function () {
    $container = Docker::redis();

    expect($container)->toBeInstanceOf(DockerContainer::class)
        ->and($container->image->value)->toBe('redis:latest')
        ->and($container->portMappings)->toHaveCount(1)
        ->and($container->name?->value)->toStartWith('redis-');
});

it('registers custom services', function () {
    Docker::register('mailhog', [
        'image'       => 'mailhog/mailhog',
        'ports'       => [1025 => 1025, 8025 => 8025],
        'name_prefix' => 'mailhog',
    ]);

    /** @phpstan-ignore-next-line staticMethod.notFound - Dynamic method via __callStatic */
    $result = Docker::mailhog();

    /** @var \Ninja\Docker\DockerContainer $container */
    $container = $result;

    expect($container)->toBeInstanceOf(DockerContainer::class)
        ->and($container->image->value)->toBe('mailhog/mailhog')
        ->and($container->portMappings)->toHaveCount(2);
});

it('throws for unregistered service', function () {
    /** @phpstan-ignore-next-line staticMethod.notFound - Testing dynamic method that should fail */
    expect(fn() => Docker::unknown())
        ->toThrow(BadMethodCallException::class, 'not registered');
});

it('prevents overriding built-in services', function () {
    expect(fn() => Docker::register('nginx', [
        'image'       => 'nginx:custom',
        'name_prefix' => 'nginx',
    ]))
        ->toThrow(InvalidArgumentException::class, 'Cannot override built-in');
});

it('requires image in service definition', function () {
    expect(fn() => Docker::register('invalid', [
        'name_prefix' => 'invalid',
    ]))
        ->toThrow(InvalidArgumentException::class, 'must include "image"');
});

it('requires name_prefix in service definition', function () {
    expect(fn() => Docker::register('invalid', [
        'image' => 'invalid:latest',
    ]))
        ->toThrow(InvalidArgumentException::class, 'must include "name_prefix"');
});

it('allows port override via config', function () {
    $container = Docker::postgres(['port' => 5433]);

    /** @var \Ninja\Docker\PortMapping $portMapping */
    $portMapping = $container->portMappings[0];
    expect($portMapping->portOnHost->value)->toBe(5433)
        ->and($portMapping->portOnDocker->value)->toBe(5432);
});

it('uses default port when not overridden', function () {
    $container = Docker::postgres();

    /** @var \Ninja\Docker\PortMapping $portMapping */
    $portMapping = $container->portMappings[0];
    expect($portMapping->portOnHost->value)->toBe(5432)
        ->and($portMapping->portOnDocker->value)->toBe(5432);
});

it('maps mysql password from config', function () {
    $container = Docker::mysql(['password' => 'secret123']);

    expect($container->environmentMappings)->toHaveCount(1)
        ->and((string) $container->environmentMappings[0])->toBe('MYSQL_ROOT_PASSWORD=secret123');
});

it('maps postgres password from config', function () {
    $container = Docker::postgres(['password' => 'pg_secret']);

    expect($container->environmentMappings)->toHaveCount(1)
        ->and((string) $container->environmentMappings[0])->toBe('POSTGRES_PASSWORD=pg_secret');
});

it('maps multiple mysql env vars', function () {
    $container = Docker::mysql([
        'password'      => 'root_secret',
        'database'      => 'myapp',
        'user'          => 'appuser',
        'user_password' => 'user_secret',
    ]);

    expect($container->environmentMappings)->toHaveCount(4);

    $envStrings = array_map(fn($m) => (string) $m, $container->environmentMappings);
    expect($envStrings)->toContain('MYSQL_ROOT_PASSWORD=root_secret')
        ->and($envStrings)->toContain('MYSQL_DATABASE=myapp')
        ->and($envStrings)->toContain('MYSQL_USER=appuser')
        ->and($envStrings)->toContain('MYSQL_PASSWORD=user_secret');
});
