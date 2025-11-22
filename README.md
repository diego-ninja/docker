# 🐋 Docker for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/diego-ninja/docker.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://packagist.org/packages/diego-ninja/granite)
[![Total Downloads](https://img.shields.io/packagist/dt/diego-ninja/docker.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://packagist.org/packages/diego-ninja/granite)
![PHP Version](https://img.shields.io/packagist/php-v/diego-ninja/docker.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://opensource.org/licenses/MIT)
![GitHub last commit](https://img.shields.io/github/last-commit/diego-ninja/docker?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)
[![wakatime](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/7c91e5ba-b5fb-41e1-8692-0bb6a2f1e0e6.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/3cc2ec60-a8b4-4ddc-aeac-ea78e37a094b)

[![Tests](https://img.shields.io/github/actions/workflow/status/diego-ninja/docker/run-tests.yml?branch=main&style=flat-square&logo=github&label=tests&logoColor=%23949ca4&labelColor=%233f4750)]()
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/diego-ninja/docker/static-code-analysis.yml?branch=main&style=flat-square&logo=github&label=phpstan%2010&logoColor=%23949ca4&labelColor=%233f4750)]()
[![Code Style](https://img.shields.io/github/actions/workflow/status/diego-ninja/docker/php-cs-fixer.yml?branch=main&style=flat-square&logo=github&label=style%3A%20PER&logoColor=%23949ca4&labelColor=%233f4750)]()
[![Coveralls](https://img.shields.io/coverallsCoverage/github/diego-ninja/docker?branch=main&style=flat-square&logo=coveralls&logoColor=%23949ca4&labelColor=%233f4750&link=https%3A%2F%2Fcoveralls.io%2Fgithub%2Fdiego-ninja%2Fdocker)]()

This package provides a fluent, modern API to start and manage Docker containers directly from your PHP code. It is a fork of `spatie/docker` with significant improvements in ergonomics, security, and functionality.

## Key Features

- ✅ **Ergonomic Facade API**: A static `Docker` facade that simplifies container creation (`Docker::nginx()->start()`).
- ✅ **Type-Safe**: Thanks to PHP 8.4 and the intensive use of immutable Value Objects (`ImageName`, `ContainerPath`, `Port`) to validate all inputs.
- ✅ **Secure**: Prevents command injection by validating all parameters through Value Objects.
- ✅ **Remote Host Support**: Run containers on remote machines via SSH.
- ✅ **SSH Agent Forwarding**: Securely allows containers to access the host's SSH keys.
- ✅ **Extensible**: Register your own custom services on the `Facade` to reuse configurations.
- ✅ **Well-Tested**: Over 95% test coverage.

## Installation

You can install the package via Composer. It requires PHP 8.4 or higher.

```bash
composer require diego-ninja/docker
```

## Quick Start

The easiest way to use the package is through the `Docker` facade.

```php
use Ninja\Docker\Docker;

// Start a Nginx container on port 8080
$container = Docker::image('nginx:latest')
    ->port(8080, 80)
    ->name('my-nginx')
    ->start();

echo "Container started. IP: " . $container->getIp() . "\n";

// Execute a command inside the container
$output = $container->execute('ls -la /usr/share/nginx/html');
echo $output;

// Stop and remove the container
$container->stop();
```

## Key Differences with `spatie/docker`

This package is a fork of [spatie/docker](https://github.com/spatie/docker), but it introduces significant improvements:

1.  **Facade for a Simplified API**: The `Docker` facade provides a static, expressive, and easy-to-remember API, ideal for frameworks like Laravel or for quick usage.
    ```php
    // Before (and still works)
    (new DockerContainer('nginx:latest'))->port(8080, 80)->start();

    // Now (recommended)
    Docker::image('nginx:latest')->port(8080, 80)->start();
    ```
2.  **Type Safety with Value Objects**: Instead of using raw strings and numbers, the library uses Value Objects to validate every parameter, preventing common errors and attacks.
    ```php
    // Encourages using Value Objects for clarity and safety
    use Ninja\Docker\ValueObjects\HostPath;
    use Ninja\Docker\ValueObjects\ContainerPath;

    Docker::image('...')->volume(
        HostPath::from(__DIR__ . '/content'),
        ContainerPath::from('/usr/share/nginx/html')
    );
    ```
3. **Specific Exceptions**: More descriptive exceptions, like `CouldNotStartDockerContainer`, have been added to make debugging easier.

## Detailed Usage

### 1. Using the Facade (Recommended)

The `Docker` facade offers three ways to create containers:

#### a) Shortcuts for Common Services

It includes shortcuts for the most popular services with pre-defined configurations.

```php
// Nginx on port 80
Docker::nginx()->start();

// MySQL 8 with a root password
Docker::mysql(['password' => 'secret'])->start();

// PostgreSQL 16 with a password
Docker::postgres(['password' => 'secret', 'database' => 'my_app'])->start();

// Redis
Docker::redis()->start();
```

You can pass a configuration array to customize the service:

```php
// MySQL with a database, user, and persistent data directory
Docker::mysql([
    'password'      => 'root_secret',
    'database'      => 'my_app',
    'user'          => 'app_user',
    'user_password' => 'user_pass',
    'data_dir'      => '/path/on/host/for/data', // Volume mount for persistence
    'port'          => 3307, // Custom host port
])->start();
```

#### b) Generic `container()` Builder

For any image that doesn't have a shortcut, use the `container()` method.

```php
Docker::container('alpine:latest')
    ->command('sleep', '300') // Keeps the container running
    ->start();
```

#### c) Registering Custom Services

You can register your own shortcuts, for example, in your application's bootstrap file.

```php
// Register a service once
Docker::register('mailhog', [
    'image'       => 'mailhog/mailhog',
    'ports'       => [1025 => 1025, 8025 => 8025],
    'name_prefix' => 'mailhog',
]);

// Use it anywhere in your code
Docker::mailhog()->start();
```

### 2. Fluent API

All facade methods return a `DockerContainer` instance, so you can continue to use the fluent API to override or add configurations.

```php
Docker::mysql(['password' => 'secret'])
    ->port(3307, 3306) // Override the default port
    ->namedVolume('mysql-data', '/var/lib/mysql') // Use a named volume
    ->network('backend') // Add to a network
    ->start();
```

### 3. Configuration Examples

#### Port Mapping

Use `port(hostPort, containerPort)`.

```php
->port(8080, 80)
```

#### Volume Mapping

- **Bind Mount**: Mounts a host path into the container.
  ```php
  use Ninja\Docker\ValueObjects\HostPath;
  use Ninja\Docker\ValueObjects\ContainerPath;

  ->volume(HostPath::from('/path/on/host'), ContainerPath::from('/path/in/container'))
  ```
- **Named Volume**: Uses a Docker-managed volume.
  ```php
  use Ninja\Docker\ValueObjects\VolumeName;
  use Ninja\Docker\ValueObjects\ContainerPath;

  ->namedVolume(VolumeName::from('my-volume'), ContainerPath::from('/path/in/container'))
  ```

#### Environment Variables

```php
->environment('MY_VAR', 'its_value')
->environment('ANOTHER_VAR', 'another_value')
```


### 4. Interacting with Running Containers

The `start()` method returns a `DockerContainerInstance`.

```php
$container = Docker::nginx()->start();

// Get the container's IP address
$ip = $container->getIp();

// Execute a command
$output = $container->execute('whoami'); // returns "root"

// Get the container ID
$id = $container->getContainerId();

// Check if it's running
if ($container->isRunning()) {
    // ...
}

// Stop the container
$container->stop();
```

You can also connect to an already running container.

```php
$container = DockerContainerInstance::fromExisting('my-nginx');
$container->execute('echo "Hello from an existing container"');
```

## Testing

Before running the tests for the first time, you need to build the Docker image for testing:

```bash
composer build-docker
```

Then, you can run the full test suite with [Pest](https://pestphp.com/):

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see `CONTRIBUTING.md` for details. Contributions are welcome.

## Security

If you've found a security vulnerability, please email [yosoy@diego.ninja](mailto:yosoy@diego.ninja) instead of using the issue tracker.

## Credits

- [Diego Rin Martin](https://github.com/diego-ninja)
- [Ruben Van Assche](https://github.com/rubenvanassche)
- [Freek Van der Herten](https://github.com/freekmurze)


## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.
