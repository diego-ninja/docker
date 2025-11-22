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
        ->and((string)$container->environmentMappings[0])->toBe('MYSQL_ROOT_PASSWORD=secret123');
});

it('maps postgres password from config', function () {
    $container = Docker::postgres(['password' => 'pg_secret']);

    expect($container->environmentMappings)->toHaveCount(1)
        ->and((string)$container->environmentMappings[0])->toBe('POSTGRES_PASSWORD=pg_secret');
});

it('maps multiple mysql env vars', function () {
    $container = Docker::mysql([
        'password'      => 'root_secret',
        'database'      => 'myapp',
        'user'          => 'appuser',
        'user_password' => 'user_secret',
    ]);

    expect($container->environmentMappings)->toHaveCount(4);

    $envStrings = array_map(fn($m) => (string)$m, $container->environmentMappings);
    expect($envStrings)->toContain('MYSQL_ROOT_PASSWORD=root_secret')
        ->and($envStrings)->toContain('MYSQL_DATABASE=myapp')
        ->and($envStrings)->toContain('MYSQL_USER=appuser')
        ->and($envStrings)->toContain('MYSQL_PASSWORD=user_secret');
});

it('mounts data directory for mysql', function () {
    $tempDir = sys_get_temp_dir() . '/mysql-test-' . uniqid();
    mkdir($tempDir);

    $container = Docker::mysql([
        'password' => 'secret',
        'data_dir' => $tempDir,
    ]);

    expect($container->bindMounts)->toHaveCount(1);

    /** @var \Ninja\Docker\BindMountMapping $bindMount */
    $bindMount = $container->bindMounts[0];
    expect($bindMount->source->value)->toBe($tempDir)
        ->and($bindMount->target->value)->toBe('/var/lib/mysql');

    rmdir($tempDir);
});

it('mounts data directory for postgres', function () {
    $tempDir = sys_get_temp_dir() . '/postgres-test-' . uniqid();
    mkdir($tempDir);

    $container = Docker::postgres([
        'password' => 'secret',
        'data_dir' => $tempDir,
    ]);

    expect($container->bindMounts)->toHaveCount(1);

    /** @var \Ninja\Docker\BindMountMapping $bindMount */
    $bindMount = $container->bindMounts[0];
    expect($bindMount->source->value)->toBe($tempDir)
        ->and($bindMount->target->value)->toBe('/var/lib/postgresql/data');

    rmdir($tempDir);
});

it('does not mount data directory when not specified', function () {
    $container = Docker::mysql(['password' => 'secret']);

    expect($container->bindMounts)->toHaveCount(0);
});

it('allows name override via config', function () {
    $container = Docker::nginx(['name' => 'my-custom-nginx']);

    expect($container->name?->value)->toBe('my-custom-nginx');
});

it('generates unique names by default', function () {
    $container1 = Docker::nginx();
    $container2 = Docker::nginx();

    expect($container1->name?->value)->not->toBe($container2->name?->value)
        ->and($container1->name?->value)->toStartWith('nginx-')
        ->and($container2->name?->value)->toStartWith('nginx-');
});

it('creates generic container from image', function () {
    $container = Docker::container('alpine:latest');

    expect($container)->toBeInstanceOf(DockerContainer::class)
        ->and($container->image->value)->toBe('alpine:latest');
});

it('creates generic container with config', function () {
    $container = Docker::container('alpine:latest', [
        'name' => 'my-alpine',
    ]);

    expect($container->name?->value)->toBe('my-alpine');
});

it('generic container supports fluent API', function () {
    $container = Docker::container('alpine:latest')
        ->mapPort(8080, 80)
        ->setEnvironmentVariable('ENV', 'test');

    expect($container->portMappings)->toHaveCount(1)
        ->and($container->environmentMappings)->toHaveCount(1);
});

it('allows fluent override after mysql shortcut', function () {
    $container = Docker::mysql(['password' => 'secret'])
        ->mapPort(3307, 3306)
        ->namedVolume('mysql-data', '/var/lib/mysql');

    /** @var \Ninja\Docker\PortMapping $portMapping */
    $portMapping = $container->portMappings[1];  // Second port (first is 3306:3306)
    expect($portMapping->portOnHost->value)->toBe(3307)
        ->and($portMapping->portOnDocker->value)->toBe(3306)
        ->and($container->namedVolumes)->toHaveCount(1);
});

it('allows fluent override after postgres shortcut', function () {
    $tempDir = sys_get_temp_dir() . '/pg-conf-' . uniqid();
    mkdir($tempDir);

    $container = Docker::postgres(['password' => 'secret'])
        ->bindMount($tempDir, '/etc/postgresql')
        ->network('backend');

    expect($container->bindMounts)->toHaveCount(1)
        ->and($container->network?->value)->toBe('backend');

    rmdir($tempDir);
});

it('allows fluent override after nginx shortcut', function () {
    $tempDir = sys_get_temp_dir() . '/nginx-conf-' . uniqid();
    mkdir($tempDir);

    $container = Docker::nginx()
        ->bindMount($tempDir, '/etc/nginx')
        ->mapPort(8080, 80)
        ->name('custom-nginx');

    expect($container->bindMounts)->toHaveCount(1)
        ->and($container->portMappings)->toHaveCount(2)  // Default 80:80 + 8080:80
        ->and($container->name?->value)->toBe('custom-nginx');

    rmdir($tempDir);
});
