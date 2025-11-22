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
