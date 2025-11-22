# Design: Docker Facade with Helper Methods

**Date:** 2025-11-22
**Author:** Diego & Claude

## Executive Summary

This design document outlines a facade layer on top of the v3.0.0 type-safe DockerContainer implementation. The facade provides ergonomic shortcuts for common Docker services while maintaining full type safety and security improvements from v3.0.0.

**Goals:**
1. Developer ergonomics - Make common tasks one-liners
2. Project-specific shortcuts - Preconfigured containers for typical stacks
3. API simplification - Hide value object complexity for simple cases
4. Extensibility - Allow registration of custom service definitions

**Approach:** Hybrid static shortcuts + fluent builder + extensible registry

---

## Architecture

### Three-Layer Approach

1. **Static Shortcuts** - Predefined services with sensible defaults
   ```php
   Docker::nginx()->start();
   Docker::mysql(['password' => 'secret'])->start();
   ```

2. **Generic Builder** - For custom images with smart defaults
   ```php
   Docker::container('custom-image')->mapPort(8080, 80)->start();
   ```

3. **Service Registry** - Extensible registration system
   ```php
   Docker::register('rabbitmq', ['image' => '...', 'ports' => [...]]);
   Docker::rabbitmq()->start();
   ```

### Design Principles

- **Composition over inheritance** - Facade delegates to DockerContainer, doesn't extend it
- **Type safety maintained** - All shortcuts use value objects internally
- **YAGNI** - Core includes only 4-5 most common services
- **Extensibility** - Registry system for project-specific services
- **Backward compatible** - DockerContainer API unchanged

---

## Service Configuration

### Configuration Array Structure

Each service is defined with a configuration array:

```php
private const SERVICES = [
    'nginx' => [
        'image' => 'nginx:latest',
        'ports' => [80 => 80],
        'name_prefix' => 'nginx',
    ],
    'mysql' => [
        'image' => 'mysql:8',
        'ports' => [3306 => 3306],
        'name_prefix' => 'mysql',
        'env_vars' => ['MYSQL_ROOT_PASSWORD'],
        'volumes' => ['/var/lib/mysql'],
    ],
    'postgres' => [
        'image' => 'postgres:16',
        'ports' => [5432 => 5432],
        'name_prefix' => 'postgres',
        'env_vars' => ['POSTGRES_PASSWORD'],
        'volumes' => ['/var/lib/postgresql/data'],
    ],
    'redis' => [
        'image' => 'redis:latest',
        'ports' => [6379 => 6379],
        'name_prefix' => 'redis',
    ],
];
```

### Service Definition Schema

```php
[
    'image' => string,              // Docker image (required)
    'ports' => [host => container], // Default port mappings (optional)
    'name_prefix' => string,        // Container name prefix (required)
    'env_vars' => [string],         // Required environment variables (optional)
    'volumes' => [string],          // Common data directories (optional)
]
```

### Built-in Services

**Core services (included by default):**
- `nginx` - Web server (port 80)
- `mysql` - MySQL 8 database (port 3306)
- `postgres` - PostgreSQL 16 database (port 5432)
- `redis` - Redis cache/store (port 6379)

These cover ~80% of development use cases. Additional services via registry.

---

## API Design

### Static Shortcuts (Predefined Services)

```php
final class Docker
{
    public static function nginx(array $config = []): DockerContainer
    {
        return self::createFromService('nginx', $config);
    }

    public static function mysql(array $config = []): DockerContainer
    {
        return self::createFromService('mysql', $config);
    }

    public static function postgres(array $config = []): DockerContainer
    {
        return self::createFromService('postgres', $config);
    }

    public static function redis(array $config = []): DockerContainer
    {
        return self::createFromService('redis', $config);
    }
}
```

### Generic Builder

```php
public static function container(string $image, array $config = []): DockerContainer
{
    return self::createGeneric($image, $config);
}
```

**Usage:**
```php
Docker::container('alpine:latest')
    ->mapPort(8080, 80)
    ->setEnvironmentVariable('ENV', 'production')
    ->start();
```

### Service Registry (Extensibility)

```php
private static array $customServices = [];

public static function register(string $name, array $config): void
{
    if (isset(self::SERVICES[$name])) {
        throw new InvalidArgumentException(
            "Cannot override built-in service '{$name}'"
        );
    }

    self::$customServices[$name] = $config;
}

public static function __callStatic(string $method, array $args): DockerContainer
{
    if (isset(self::SERVICES[$method]) || isset(self::$customServices[$method])) {
        return self::createFromService($method, $args[0] ?? []);
    }

    throw new BadMethodCallException("Service '{$method}' not registered");
}
```

**Usage:**
```php
// Register custom service
Docker::register('mailhog', [
    'image' => 'mailhog/mailhog',
    'ports' => [1025 => 1025, 8025 => 8025],
    'name_prefix' => 'mailhog',
]);

// Use like built-in service
Docker::mailhog()->start();
```

---

## Core Factory Method

### createFromService() Implementation

```php
private static function createFromService(string $service, array $config): DockerContainer
{
    $definition = self::SERVICES[$service] ?? self::$customServices[$service] ?? null;

    if ($definition === null) {
        throw new InvalidArgumentException(
            "Service '{$service}' not registered. Available: " .
            implode(', ', array_keys(array_merge(self::SERVICES, self::$customServices)))
        );
    }

    // Create container with image
    $container = DockerContainer::create($definition['image']);

    // Apply default port mappings
    foreach ($definition['ports'] ?? [] as $hostPort => $containerPort) {
        $actualHostPort = $config['port'] ?? $hostPort;
        $container->mapPort($actualHostPort, $containerPort);
    }

    // Apply environment variables from config
    $envMapping = self::getEnvMapping($service);
    foreach ($definition['env_vars'] ?? [] as $envVar) {
        $configKey = $envMapping[$envVar] ?? strtolower($envVar);
        if (isset($config[$configKey])) {
            $container->setEnvironmentVariable($envVar, $config[$configKey]);
        }
    }

    // Apply data directory bind mount if specified
    if (isset($config['data_dir']) && !empty($definition['volumes'])) {
        $container->bindMount($config['data_dir'], $definition['volumes'][0]);
    }

    // Auto-generate unique name
    $container->name(self::generateName($definition['name_prefix']));

    return $container;
}
```

### Environment Variable Mapping

Maps friendly config keys to service-specific env var names:

```php
private static function getEnvMapping(string $service): array
{
    return match($service) {
        'mysql' => [
            'MYSQL_ROOT_PASSWORD' => 'password',
            'MYSQL_DATABASE' => 'database',
            'MYSQL_USER' => 'user',
            'MYSQL_PASSWORD' => 'user_password',
        ],
        'postgres' => [
            'POSTGRES_PASSWORD' => 'password',
            'POSTGRES_USER' => 'user',
            'POSTGRES_DB' => 'database',
        ],
        default => [],
    };
}
```

### Name Generation

```php
private static function generateName(string $prefix): string
{
    return sprintf('%s-%s', $prefix, substr(uniqid(), -8));
    // Generates: nginx-a1b2c3d4, mysql-e5f6g7h8
}
```

---

## Configuration Array Options

### Common Options (All Services)

```php
[
    'port' => int,      // Override default host port
    'name' => string,   // Override auto-generated name
]
```

### Service-Specific Options

**MySQL:**
```php
[
    'password' => string,       // MYSQL_ROOT_PASSWORD (required for first start)
    'database' => string,       // MYSQL_DATABASE
    'user' => string,          // MYSQL_USER
    'user_password' => string, // MYSQL_PASSWORD
    'data_dir' => string,      // Bind mount for /var/lib/mysql
]
```

**PostgreSQL:**
```php
[
    'password' => string,  // POSTGRES_PASSWORD (required)
    'user' => string,      // POSTGRES_USER (default: postgres)
    'database' => string,  // POSTGRES_DB (default: postgres)
    'data_dir' => string,  // Bind mount for /var/lib/postgresql/data
]
```

**Nginx:**
```php
[
    'content_dir' => string,  // Bind mount for /usr/share/nginx/html
]
```

---

## Usage Examples

### Quick Start (Minimal Config)

```php
// Nginx with defaults (port 80)
Docker::nginx()->start();

// MySQL with just password
Docker::mysql(['password' => 'secret'])->start();

// Redis with defaults (port 6379)
Docker::redis()->start();
```

### Configuration via Array

```php
// MySQL with database and user
Docker::mysql([
    'password' => 'root_secret',
    'database' => 'myapp',
    'user' => 'appuser',
    'user_password' => 'user_secret',
])->start();

// PostgreSQL with custom port
Docker::postgres([
    'password' => 'secret',
    'database' => 'mydb',
    'port' => 5433,  // Override default 5432
])->start();

// Nginx with content directory
Docker::nginx([
    'content_dir' => '/path/to/html',
])->start();
```

### Override Defaults with Fluent API

```php
// Start with config, then override
Docker::mysql(['password' => 'secret'])
    ->mapPort(3307, 3306)  // Different host port
    ->namedVolume('mysql-data', '/var/lib/mysql')  // Named volume instead of bind mount
    ->network('backend')  // Add to network
    ->start();

// Complex nginx setup
Docker::nginx()
    ->bindMount('/path/to/nginx.conf', '/etc/nginx/nginx.conf')
    ->bindMount('/path/to/html', '/usr/share/nginx/html')
    ->mapPort(8080, 80)
    ->name('my-nginx')
    ->start();
```

### Custom Services via Registry

```php
// Register once (bootstrap/config)
Docker::register('rabbitmq', [
    'image' => 'rabbitmq:3-management',
    'ports' => [5672 => 5672, 15672 => 15672],
    'name_prefix' => 'rabbitmq',
    'env_vars' => ['RABBITMQ_DEFAULT_USER', 'RABBITMQ_DEFAULT_PASS'],
]);

Docker::register('mailhog', [
    'image' => 'mailhog/mailhog',
    'ports' => [1025 => 1025, 8025 => 8025],
    'name_prefix' => 'mailhog',
]);

// Use throughout application
Docker::rabbitmq([
    'rabbitmq_default_user' => 'admin',
    'rabbitmq_default_pass' => 'secret',
])->start();

Docker::mailhog()->start();
```

### Generic Container Builder

```php
// Custom image without registration
Docker::container('custom/app:latest')
    ->mapPort(8080, 80)
    ->setEnvironmentVariable('APP_ENV', 'production')
    ->bindMount('/path/to/config', '/app/config')
    ->start();
```

---

## Implementation Details

### File Structure

```
src/
├── Docker.php                    # Main facade class
├── DockerContainer.php           # Existing v3.0.0 class
└── ValueObjects/                 # Existing value objects
    ├── Port.php
    ├── ContainerName.php
    └── ...

tests/
├── DockerFacadeTest.php         # New facade tests
└── DockerContainerTest.php      # Existing tests
```

### Class Structure

```php
<?php

declare(strict_types=1);

namespace Ninja\Docker;

use BadMethodCallException;
use InvalidArgumentException;

final class Docker
{
    /** @var array<string, array<string, mixed>> */
    private const SERVICES = [...];

    /** @var array<string, array<string, mixed>> */
    private static array $customServices = [];

    // Static shortcuts
    public static function nginx(array $config = []): DockerContainer { ... }
    public static function mysql(array $config = []): DockerContainer { ... }
    public static function postgres(array $config = []): DockerContainer { ... }
    public static function redis(array $config = []): DockerContainer { ... }

    // Generic builder
    public static function container(string $image, array $config = []): DockerContainer { ... }

    // Registry
    public static function register(string $name, array $config): void { ... }
    public static function __callStatic(string $method, array $args): DockerContainer { ... }

    // Factory methods (private)
    private static function createFromService(string $service, array $config): DockerContainer { ... }
    private static function createGeneric(string $image, array $config): DockerContainer { ... }
    private static function getEnvMapping(string $service): array { ... }
    private static function generateName(string $prefix): string { ... }
}
```

---

## Testing Strategy

### Unit Tests

```php
it('creates nginx container with default config', function () {
    $container = Docker::nginx();

    expect($container)->toBeInstanceOf(DockerContainer::class)
        ->and($container->image->value)->toBe('nginx:latest')
        ->and($container->portMappings)->toHaveCount(1);
});

it('creates mysql with password from config', function () {
    $container = Docker::mysql(['password' => 'secret']);

    expect($container->environmentMappings)->toHaveCount(1)
        ->and((string) $container->environmentMappings[0])->toBe('MYSQL_ROOT_PASSWORD=secret');
});

it('allows port override via config', function () {
    $container = Docker::postgres(['password' => 'secret', 'port' => 5433]);

    $portMapping = $container->portMappings[0];
    expect($portMapping->portOnHost->value)->toBe(5433)
        ->and($portMapping->portOnDocker->value)->toBe(5432);
});

it('registers custom services', function () {
    Docker::register('custom', [
        'image' => 'custom:latest',
        'ports' => [8080 => 80],
    ]);

    $container = Docker::custom();
    expect($container->image->value)->toBe('custom:latest');
});

it('throws for unknown service', function () {
    expect(fn() => Docker::unknown())
        ->toThrow(BadMethodCallException::class, 'not registered');
});

it('prevents overriding built-in services', function () {
    expect(fn() => Docker::register('nginx', ['image' => 'nginx:custom']))
        ->toThrow(InvalidArgumentException::class, 'Cannot override built-in');
});
```

### Integration Tests

```php
it('starts nginx container and responds on port 80', function () {
    $container = Docker::nginx()->start();

    // Wait for nginx to be ready
    sleep(2);

    $response = file_get_contents('http://localhost:80');
    expect($response)->toContain('Welcome to nginx');

    $container->stop();
});

it('starts mysql with persistent data', function () {
    $tempDir = sys_get_temp_dir() . '/mysql-test';
    mkdir($tempDir);

    $container = Docker::mysql([
        'password' => 'secret',
        'database' => 'testdb',
        'data_dir' => $tempDir,
    ])->start();

    // Verify MySQL is running
    sleep(5);
    expect($container->isRunning())->toBeTrue();

    $container->stop();
    rmdir($tempDir);
});
```

---

## Type Safety

### PHPStan Compliance

All methods maintain PHPStan level 10 compliance:

```php
/**
 * @param array{
 *     password?: string,
 *     database?: string,
 *     user?: string,
 *     user_password?: string,
 *     data_dir?: string,
 *     port?: int,
 *     name?: string
 * } $config
 */
public static function mysql(array $config = []): DockerContainer
{
    return self::createFromService('mysql', $config);
}
```

### Value Object Usage

Facade internally uses all v3.0.0 value objects:

```php
// Ports validated via Port VO
$container->mapPort($hostPort, $containerPort);

// Paths validated via HostPath/ContainerPath VOs
$container->bindMount($config['data_dir'], $definition['volumes'][0]);

// Names validated via ContainerName VO
$container->name(self::generateName($prefix));

// Env vars validated via EnvironmentVariable VO
$container->setEnvironmentVariable($key, $value);
```

---

## Security Considerations

### Input Validation

All user inputs validated through value objects:
- Port numbers: 1-65535 range (Port VO)
- Container names: Docker naming rules (ContainerName VO)
- Paths: Absolute, exists, readable (HostPath VO)
- Environment variables: POSIX naming (EnvironmentVariable VO)

### No Additional Attack Surface

Facade adds **zero** new security risks:
- No direct shell execution (delegates to DockerContainer)
- No string interpolation (uses value objects)
- No bypass of v3.0.0 security features
- Configuration arrays validated before use

### Service Definition Validation

```php
public static function register(string $name, array $config): void
{
    // Validate required fields
    if (!isset($config['image'])) {
        throw new InvalidArgumentException('Service config must include "image"');
    }

    if (!isset($config['name_prefix'])) {
        throw new InvalidArgumentException('Service config must include "name_prefix"');
    }

    // Prevent override of built-in services
    if (isset(self::SERVICES[$name])) {
        throw new InvalidArgumentException("Cannot override built-in service '{$name}'");
    }

    self::$customServices[$name] = $config;
}
```

---

## Backward Compatibility

### No Breaking Changes

- DockerContainer API remains unchanged
- Existing code continues to work
- Facade is **additive only**
- Users can adopt facade gradually

### Migration Path

```php
// Old style (still works)
DockerContainer::create('nginx:latest')
    ->mapPort(80, 80)
    ->name('my-nginx')
    ->start();

// New facade (more ergonomic)
Docker::nginx()->start();

// Both can coexist
$nginx = Docker::nginx();
$custom = DockerContainer::create('custom:latest');
```

---

## Future Enhancements (Not in Scope)

### Potential Additions (YAGNI - only if needed)

1. **Service groups/stacks**:
   ```php
   Docker::stack(['nginx', 'mysql', 'redis'])
       ->withNetwork('app-network')
       ->start();
   ```

2. **Health checks**:
   ```php
   Docker::mysql(['password' => 'secret'])
       ->waitUntilHealthy(timeout: 30)
       ->start();
   ```

3. **Service-specific fluent wrappers**:
   ```php
   Docker::mysql()
       ->withPassword('secret')
       ->withDatabase('myapp')
       ->withUser('appuser', 'userpass')
       ->start();
   ```

4. **Docker Compose integration**:
   ```php
   Docker::fromCompose('docker-compose.yml')
       ->service('web')
       ->start();
   ```

**Decision:** Keep initial implementation minimal. Add these only if users request them.

---

## Success Criteria

### Must Have
- [ ] Static shortcuts for nginx, mysql, postgres, redis
- [ ] Configuration array support with common options
- [ ] Service registry for custom services
- [ ] Generic container() builder
- [ ] 100% test coverage for facade
- [ ] PHPStan level 10 compliance
- [ ] All shortcuts use v3.0.0 value objects internally
- [ ] Comprehensive documentation with examples

### Nice to Have
- [ ] Environment variable mapping for common services
- [ ] Auto-generated unique container names
- [ ] Fluent API works after shortcuts
- [ ] Clear error messages for invalid config

### Success Metrics
- Reduces common use cases from 3-5 lines to 1 line
- No new security vulnerabilities introduced
- Maintains type safety (PHPStan level 10)
- Documentation covers all use cases

---

## Implementation Plan

### Phase 1: Core Facade
1. Create `src/Docker.php` with service definitions
2. Implement static shortcuts (nginx, mysql, postgres, redis)
3. Implement `createFromService()` factory method
4. Add name generation
5. Write unit tests (100% coverage)

### Phase 2: Registry System
1. Add `$customServices` static property
2. Implement `register()` method
3. Implement `__callStatic()` for dynamic services
4. Add validation for service definitions
5. Write tests for registration

### Phase 3: Configuration
1. Implement environment variable mapping
2. Add port override support
3. Add data directory bind mount support
4. Add name override support
5. Write tests for all config options

### Phase 4: Integration Tests
1. Add integration test for each built-in service
2. Test custom service registration
3. Test fluent override after shortcuts
4. Verify no regressions in DockerContainer

### Phase 5: Documentation
1. Update README with facade examples
2. Add facade section to docs
3. Add migration examples (old vs new style)
4. Document all config options

---

## Conclusion

This facade design provides an ergonomic layer on top of the type-safe v3.0.0 implementation without sacrificing security or type safety. The hybrid approach (static shortcuts + registry) balances YAGNI with extensibility, while the configuration array + fluent API combination serves both simple and complex use cases.

**Key Benefits:**
- **Ergonomic**: Common tasks become one-liners
- **Type-safe**: All inputs validated via value objects
- **Secure**: Zero additional attack surface
- **Extensible**: Registry for project-specific services
- **Backward compatible**: Existing code unaffected
- **Well-tested**: 100% coverage maintained

**Next Steps:**
1. Review and approve design
2. Create implementation plan
3. Implement in phases
4. Document and release as part of v3.1.0 (minor version - additive only)
