<?php

// ABOUTME: Facade for creating Docker containers with sensible defaults.
// ABOUTME: Provides shortcuts for common services and extensible registry.

declare(strict_types=1);

namespace Ninja\Docker;

use BadMethodCallException;
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

    /** @var array<string, array{image: string, ports?: array<int, int>, name_prefix: string, env_vars?: list<string>, volumes?: list<string>}> */
    private static array $customServices = [];

    /**
     * @param array<string, mixed> $config
     */
    public static function register(string $name, array $config): void
    {
        // Validate required fields
        if (!isset($config['image']) || !is_string($config['image'])) {
            throw new InvalidArgumentException('Service config must include "image"');
        }

        if (!isset($config['name_prefix']) || !is_string($config['name_prefix'])) {
            throw new InvalidArgumentException('Service config must include "name_prefix"');
        }

        // Prevent override of built-in services
        if (isset(self::SERVICES[$name])) {
            throw new InvalidArgumentException(
                sprintf("Cannot override built-in service '%s'", $name)
            );
        }

        /** @var array{image: string, ports?: array<int, int>, name_prefix: string, env_vars?: list<string>, volumes?: list<string>} $config */
        self::$customServices[$name] = $config;
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function __callStatic(string $method, array $args): DockerContainer
    {
        if (isset(self::SERVICES[$method]) || isset(self::$customServices[$method])) {
            $firstArg = $args[0] ?? [];
            /** @var array<string, mixed> $config */
            $config = is_array($firstArg) ? $firstArg : [];
            return self::createFromService($method, $config);
        }

        throw new BadMethodCallException(
            sprintf(
                "Service '%s' not registered. Available: %s",
                $method,
                implode(', ', array_keys(array_merge(self::SERVICES, self::$customServices)))
            )
        );
    }

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
    public static function mysql(array $config = []): DockerContainer
    {
        return self::createFromService('mysql', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function postgres(array $config = []): DockerContainer
    {
        return self::createFromService('postgres', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function redis(array $config = []): DockerContainer
    {
        return self::createFromService('redis', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createFromService(string $service, array $config): DockerContainer
    {
        $definition = self::SERVICES[$service] ?? self::$customServices[$service] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException(
                sprintf(
                    "Service '%s' not registered. Available: %s",
                    $service,
                    implode(', ', array_keys(array_merge(self::SERVICES, self::$customServices)))
                )
            );
        }

        // Create container with image
        $container = DockerContainer::create($definition['image']);

        // Apply default port mappings with optional override
        foreach ($definition['ports'] ?? [] as $hostPort => $containerPort) {
            /** @var int $actualHostPort */
            $actualHostPort = $config['port'] ?? $hostPort;
            $container->mapPort($actualHostPort, $containerPort);
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
