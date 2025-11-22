<?php

// ABOUTME: Facade for creating Docker containers with sensible defaults.
// ABOUTME: Provides shortcuts for common services and extensible registry.

declare(strict_types=1);

namespace Ninja\Docker;

use InvalidArgumentException;

final class Docker
{
    /** @var array<string, array{image: string, ports?: array<int, int>, name_prefix: string, env_vars?: list<string>, volumes?: list<string>}> */
    private const SERVICES = [
        'nginx' => [
            'image'       => 'nginx:latest',
            'ports'       => [80 => 80],
            'name_prefix' => 'nginx',
        ],
        'mysql' => [
            'image'       => 'mysql:8',
            'ports'       => [3306 => 3306],
            'name_prefix' => 'mysql',
            'env_vars'    => ['MYSQL_ROOT_PASSWORD'],
            'volumes'     => ['/var/lib/mysql'],
        ],
        'postgres' => [
            'image'       => 'postgres:16',
            'ports'       => [5432 => 5432],
            'name_prefix' => 'postgres',
            'env_vars'    => ['POSTGRES_PASSWORD'],
            'volumes'     => ['/var/lib/postgresql/data'],
        ],
        'redis' => [
            'image'       => 'redis:latest',
            'ports'       => [6379 => 6379],
            'name_prefix' => 'redis',
        ],
    ];

    /**
     * @param array<string, mixed> $config
     */
    public static function nginx(array $config = []): DockerContainer
    {
        return self::createFromService('nginx', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createFromService(string $service, array $config): DockerContainer
    {
        $definition = self::SERVICES[$service] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException(
                sprintf(
                    "Service '%s' not registered. Available: %s",
                    $service,
                    implode(', ', array_keys(self::SERVICES))
                )
            );
        }

        // Create container with image
        $container = DockerContainer::create($definition['image']);

        // Apply default port mappings
        /** @phpstan-ignore-next-line nullCoalesce.offset - Future-proofing for custom services without ports */
        foreach ($definition['ports'] ?? [] as $hostPort => $containerPort) {
            $container->mapPort($hostPort, $containerPort);
        }

        // Auto-generate unique name
        $container->name(self::generateName($definition['name_prefix']));

        return $container;
    }

    private static function generateName(string $prefix): string
    {
        return sprintf('%s-%s', $prefix, substr(uniqid(), -8));
    }
}
