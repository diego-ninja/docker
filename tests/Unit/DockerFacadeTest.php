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

    expect($container->portMappings)->toHaveCount(1)
        ->and($container->portMappings[0]->portOnHost->value)->toBe(80)
        ->and($container->portMappings[0]->portOnDocker->value)->toBe(80);
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
