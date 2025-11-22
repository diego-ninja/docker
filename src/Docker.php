<?php

// ABOUTME: Facade for creating Docker containers with sensible defaults.
// ABOUTME: Provides shortcuts for common services and extensible registry.

declare(strict_types=1);

namespace Ninja\Docker;

final class Docker
{
    /**
     * @param array<string, mixed> $config
     */
    public static function nginx(array $config = []): DockerContainer
    {
        return DockerContainer::create('nginx:latest');
    }
}
