# Upgrading from 2.x to 3.0.0

## Breaking Changes

### 1. Command Methods Return Arrays

**Before (2.x):**
```php
$command = $container->getStartCommand(); // string
shell_exec($command);
```

**After (3.0.0):**
```php
$command = $container->getStartCommand(); // array
(new Process($command))->run();
```

### 2. Volume Methods

**Before (2.x):**
```php
$container->setVolume('/host/data', '/container/data');
```

**After (3.0.0):**
```php
// For bind mounts (host filesystem)
$container->bindMount('/host/data', '/container/data');

// For Docker managed volumes
$container->namedVolume('data-volume', '/container/data');
```

The old `setVolume()` is deprecated but still works by auto-detecting bind mount vs named volume.

### 3. Stricter Validation

All inputs are now validated immediately:

**Before (2.x):**
```php
$container->mapPort(-1, 80); // Accepted, failed at runtime
$container->name('INVALID NAME'); // Accepted, Docker rejected
```

**After (3.0.0):**
```php
$container->mapPort(-1, 80); // throws InvalidArgumentException
$container->name('INVALID NAME'); // throws InvalidArgumentException
```

### 4. Property Access Changes

**Before (2.x):**
```php
$container->portMappings[] = new PortMapping(8080, 80); // Could mutate directly
```

**After (3.0.0):**
```php
// Properties are read-only, use setter methods
$container->mapPort(8080, 80);

// Can still read
$mappings = $container->portMappings; // array, but read-only
```

## Security Improvements

- **Command injection prevention**: All commands use array-based execution
- **Input validation**: Invalid ports, names, paths rejected immediately
- **Type safety**: Value objects guarantee valid configuration

## Migration Guide

Most code requires minimal changes:

```php
// This still works in 3.0.0
DockerContainer::create('nginx:latest')
    ->mapPort(8080, 80)
    ->name('my-container')
    ->bindMount('/host/data', '/container/data')  // was setVolume()
    ->start();
```

Only change needed: `setVolume()` → `bindMount()` or `namedVolume()`
