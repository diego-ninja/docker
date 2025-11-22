# Docker Facade Usage Guide

The `Docker` facade provides an ergonomic API for creating Docker containers with sensible defaults. It's built on top of the type-safe v3.0.0 `DockerContainer` implementation and maintains full type safety while reducing boilerplate.

## Philosophy

**Before (v3.0.0):**
```php
DockerContainer::create('mysql:8')
    ->mapPort(3306, 3306)
    ->setEnvironmentVariable('MYSQL_ROOT_PASSWORD', 'secret')
    ->name('mysql-app')
    ->start();
```

**After (v3.1.0):**
```php
Docker::mysql(['password' => 'secret'])->start();
```

Same security, same type safety, less code.

## Built-in Services

### Nginx

**Default Configuration:**
- Image: `nginx:latest`
- Port: `80:80`
- Name: `nginx-<random>`

**Options:**
- `port` - Override host port (default: 80)
- `name` - Override container name

**Examples:**

```php
// Minimal
Docker::nginx()->start();

// Custom port
Docker::nginx(['port' => 8080])->start();

// Full control with fluent API
Docker::nginx()
    ->bindMount('/path/to/nginx.conf', '/etc/nginx/nginx.conf')
    ->bindMount('/path/to/html', '/usr/share/nginx/html')
    ->mapPort(8080, 80)
    ->start();
```

---

### MySQL

**Default Configuration:**
- Image: `mysql:8`
- Port: `3306:3306`
- Name: `mysql-<random>`
- Data Volume: `/var/lib/mysql`

**Options:**
- `password` - `MYSQL_ROOT_PASSWORD` (required for first start)
- `database` - `MYSQL_DATABASE`
- `user` - `MYSQL_USER`
- `user_password` - `MYSQL_PASSWORD`
- `data_dir` - Host path for data persistence
- `port` - Override host port
- `name` - Override container name

**Examples:**

```php
// Minimal (password required)
Docker::mysql(['password' => 'secret'])->start();

// With database
Docker::mysql([
    'password' => 'secret',
    'database' => 'myapp',
])->start();

// Full setup with user and persistent data
Docker::mysql([
    'password' => 'root_secret',
    'database' => 'myapp',
    'user' => 'appuser',
    'user_password' => 'user_secret',
    'data_dir' => '/var/lib/mysql-data',
    'port' => 3307,
])->start();

// Override with named volume
Docker::mysql(['password' => 'secret'])
    ->namedVolume('mysql-data', '/var/lib/mysql')
    ->start();
```

---

### PostgreSQL

**Default Configuration:**
- Image: `postgres:16`
- Port: `5432:5432`
- Name: `postgres-<random>`
- Data Volume: `/var/lib/postgresql/data`

**Options:**
- `password` - `POSTGRES_PASSWORD` (required)
- `user` - `POSTGRES_USER` (default: postgres)
- `database` - `POSTGRES_DB` (default: postgres)
- `data_dir` - Host path for data persistence
- `port` - Override host port
- `name` - Override container name

**Examples:**

```php
// Minimal
Docker::postgres(['password' => 'secret'])->start();

// With database and user
Docker::postgres([
    'password' => 'secret',
    'database' => 'mydb',
    'user' => 'dbuser',
])->start();

// Persistent data
Docker::postgres([
    'password' => 'secret',
    'data_dir' => '/var/lib/postgres-data',
])->start();
```

---

### Redis

**Default Configuration:**
- Image: `redis:latest`
- Port: `6379:6379`
- Name: `redis-<random>`

**Options:**
- `port` - Override host port
- `name` - Override container name

**Examples:**

```php
// Minimal
Docker::redis()->start();

// Custom port
Docker::redis(['port' => 6380])->start();
```

---

## Custom Services

Register your own service definitions:

```php
Docker::register('rabbitmq', [
    'image' => 'rabbitmq:3-management',
    'ports' => [5672 => 5672, 15672 => 15672],
    'name_prefix' => 'rabbitmq',
    'env_vars' => ['RABBITMQ_DEFAULT_USER', 'RABBITMQ_DEFAULT_PASS'],
]);

// Use like built-in service
Docker::rabbitmq([
    'rabbitmq_default_user' => 'admin',
    'rabbitmq_default_pass' => 'secret',
])->start();
```

**Service Definition Schema:**

```php
[
    'image' => string,              // Docker image (required)
    'ports' => [host => container], // Default port mappings (optional)
    'name_prefix' => string,        // Container name prefix (required)
    'env_vars' => [string],         // Required environment variables (optional)
    'volumes' => [string],          // Common data directories (optional)
]
```

**Restrictions:**
- Cannot override built-in services (nginx, mysql, postgres, redis)
- `image` and `name_prefix` are required
- Registration is global (affects all subsequent calls)

---

## Generic Container Builder

For images without service registration:

```php
Docker::container('alpine:latest')
    ->mapPort(8080, 80)
    ->setEnvironmentVariable('APP_ENV', 'production')
    ->bindMount('/path/to/config', '/app/config')
    ->start();
```

Useful for:
- One-off containers
- Testing different images
- Quick prototyping

---

## Type Safety

All shortcuts maintain PHPStan level 10 compliance:

```php
/** @param array{password?: string, database?: string, port?: int} $config */
Docker::mysql($config);  // Fully typed
```

IDEs provide autocomplete for all config options:

```php
Docker::mysql([
    'password' => '',  // ← IDE suggests: password, database, user, user_password, data_dir, port, name
]);
```

---

## Security

The facade adds **zero** new security vulnerabilities:

- All inputs validated through v3.0.0 value objects
- No shell execution (delegates to `DockerContainer`)
- No string interpolation (uses array-based commands)
- Configuration arrays validated before use
- Same command injection protection as v3.0.0

---

## Migration Guide

### From v2.x Direct Usage

```php
// v2.x style
DockerContainer::create('nginx:latest')
    ->mapPort(80, 80)
    ->name('my-nginx')
    ->start();

// v3.1.0 facade
Docker::nginx()->start();
```

### From v3.0.0

```php
// v3.0.0 (still works)
DockerContainer::create('mysql:8')
    ->mapPort(3306, 3306)
    ->setEnvironmentVariable('MYSQL_ROOT_PASSWORD', 'secret')
    ->start();

// v3.1.0 facade (more ergonomic)
Docker::mysql(['password' => 'secret'])->start();
```

### Gradual Adoption

Both styles can coexist:

```php
// Use facade for common services
$mysql = Docker::mysql(['password' => 'secret']);
$redis = Docker::redis();

// Use DockerContainer for complex custom setups
$custom = DockerContainer::create('custom:latest')
    ->mapPort(8080, 80)
    ->privileged()
    ->start();
```

---

## Best Practices

### 1. Use Shortcuts for Common Services

```php
// Good
Docker::nginx()->start();

// Overkill
DockerContainer::create('nginx:latest')->mapPort(80, 80)->name('nginx-xyz')->start();
```

### 2. Use Config Arrays for Quick Setup

```php
// Good
Docker::mysql(['password' => 'secret', 'database' => 'myapp'])->start();

// Verbose
Docker::mysql()->setEnvironmentVariable('MYSQL_ROOT_PASSWORD', 'secret')->...
```

### 3. Use Fluent API for Overrides

```php
// Good (config for basics, fluent for overrides)
Docker::mysql(['password' => 'secret'])
    ->network('backend')
    ->privileged()
    ->start();
```

### 4. Register Common Custom Services

```php
// bootstrap.php
Docker::register('mailhog', [...]);
Docker::register('elasticsearch', [...]);

// Usage anywhere
Docker::mailhog()->start();
```

### 5. Use Generic Builder for One-offs

```php
// Good (no need to register for one-time use)
Docker::container('custom/test:latest')->start();

// Overkill
Docker::register('custom-test', [...]); // Don't register one-off services
```

---

## Troubleshooting

### Service Not Found

```
BadMethodCallException: Service 'unknown' not registered
```

**Solution:** Check spelling or register the service first:

```php
Docker::register('unknown', ['image' => 'unknown:latest', 'name_prefix' => 'unknown']);
```

### Cannot Override Built-in

```
InvalidArgumentException: Cannot override built-in service 'nginx'
```

**Solution:** Use a different name:

```php
Docker::register('nginx-custom', ['image' => 'nginx:alpine', 'name_prefix' => 'nginx']);
```

### Missing Required Field

```
InvalidArgumentException: Service config must include "image"
```

**Solution:** Ensure service definition includes all required fields:

```php
Docker::register('myservice', [
    'image' => 'myservice:latest',        // Required
    'name_prefix' => 'myservice',         // Required
    'ports' => [8080 => 80],              // Optional
]);
```

---

## FAQ

**Q: Can I modify the default port for a built-in service globally?**

A: No, but you can register a custom service with different defaults:

```php
Docker::register('mysql-custom', [
    'image' => 'mysql:8',
    'ports' => [3307 => 3306],  // Different default
    'name_prefix' => 'mysql',
]);

Docker::{'mysql-custom'}()->start();
```

**Q: Can I use the facade with DockerContainerInstance?**

A: Yes, shortcuts return `DockerContainer`, so `start()` returns `DockerContainerInstance`:

```php
$instance = Docker::nginx()->start();
$instance->execute('nginx -v');
```

**Q: Is the facade slower than direct DockerContainer?**

A: No, the facade is a thin wrapper with negligible overhead (one method call).

**Q: Can I use shortcuts with remote hosts?**

A: Yes, use `remoteDockerHost()`:

```php
Docker::mysql(['password' => 'secret'])
    ->remoteDockerHost('tcp://192.168.1.100:2375')
    ->start();
```

**Q: Do shortcuts work with daemonized containers?**

A: Yes, all default to daemonized (`-d`) just like `DockerContainer`.

---

## Summary

The Docker facade provides:

- **Ergonomics**: One-liners for common tasks
- **Type Safety**: PHPStan level 10 compliance
- **Security**: Zero additional attack surface
- **Flexibility**: Config arrays + fluent API
- **Extensibility**: Custom service registration
- **Compatibility**: Works with existing v3.0.0 code

Use shortcuts for quick setup, fluent API for complex configurations.
