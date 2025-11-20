# Design: v3.0.0 Security and Quality Improvements

**Date:** 2025-11-20
**Version:** 3.0.0 (Breaking Release)
**Author:** Diego & Claude

## Executive Summary

This design document outlines comprehensive improvements to the diego-ninja/docker library for version 3.0.0, focusing on three critical areas:

1. **Security Hardening** - Eliminate command injection vulnerabilities through array-based command execution and strict input validation
2. **Test Coverage** - Increase coverage from 88% to 95%+ with hybrid unit/integration testing strategy
3. **Type Safety** - Modernize with PHP 8.4 features (property hooks, asymmetric visibility) and encapsulation improvements

This is a **breaking release** requiring major version bump due to API changes for security improvements.

---

## 1. Security Hardening

### 1.1 Problem Statement

**Command Injection Vulnerabilities:**

Current implementation builds Docker commands as shell strings with user input interpolation:

```php
$extraOptions[] = "--name {$this->name}";
$extraOptions[] = '--network ' . $this->network;
$this->execute("chmod 600 {$pathToAuthorizedKeys}");
```

**Risk:** Malicious input like `name('test; rm -rf /; #')` can execute arbitrary commands.

**Missing Input Validation:**

Methods accept primitives without validation:
```php
$container->mapPort(-1, 999999);  // Invalid ports accepted
$container->name('INVALID NAME!!!');  // Docker rejects at runtime
```

### 1.2 Solution: Array-Based Command Execution

**Approach:** Refactor all command generation to use Symfony Process array-based commands.

**Before (Vulnerable):**
```php
$command = "docker run --name {$name} {$image}";
Process::fromShellCommandline($command)->run();
```

**After (Secure):**
```php
$command = ['docker', 'run', '--name', $name, $image];
(new Process($command))->run();
```

**Implementation:**

```php
private function getStartCommand(): array
{
    $command = ['docker', 'run'];

    if ($this->cleanOnDestruct) {
        $command[] = '--rm';
    }

    if ($this->name !== null) {
        $command[] = '--name';
        $command[] = (string) $this->name;
    }

    foreach ($this->portMappings as $portMapping) {
        $command[] = '-p';
        $command[] = (string) $portMapping;
    }

    foreach ($this->bindMounts as $mount) {
        $command[] = '-v';
        $command[] = (string) $mount;
    }

    if ($this->network !== null) {
        $command[] = '--network';
        $command[] = (string) $this->network;
    }

    $command[] = (string) $this->image;

    return array_merge($command, $this->commands);
}

private function executeCommand(array $command): Process
{
    $process = new Process($command);
    $process->setTimeout(null);
    $process->run();

    return $process;
}
```

**Benefits:**
- No shell parsing, no injection possible
- Arguments with spaces/special chars handled safely
- Process isolation (no shell spawned)

### 1.3 Solution: Self-Validating Value Objects

**Approach:** Create immutable value objects that enforce validity at construction.

**Value Objects to Implement:**

1. **Port** - Validates 1-65535
2. **ContainerName** - Validates Docker name format `[a-zA-Z0-9][a-zA-Z0-9_.-]*`
3. **NetworkName** - Same validation as ContainerName
4. **ImageName** - Validates `[registry/][namespace/]repository[:tag][@digest]`
5. **HostPath** - Validates path exists and is readable
6. **VolumeName** - Validates named volume format
7. **ContainerPath** - Validates absolute path format
8. **EnvironmentVariable** - Validates key format `[a-zA-Z_][a-zA-Z0-9_]*`
9. **RemoteHost** - Validates `tcp://host:port`, `ssh://user@host`, `unix:///socket`

**Implementation Pattern:**

```php
final readonly class Port {
    /** @var positive-int */
    public private(set) int $value;

    public function __construct(int $value) {
        if ($value < 1 || $value > 65535) {
            throw new \InvalidArgumentException(
                "Port must be between 1 and 65535, got {$value}"
            );
        }
        $this->value = $value;
    }

    public static function from(int $value): self {
        return new self($value);
    }

    public function __toString(): string {
        return (string) $this->value;
    }
}
```

**All Value Objects:**
- Are `final readonly`
- Validate in constructor, throw `InvalidArgumentException` on failure
- Provide `::from()` static factory method
- Implement `__toString()` for command generation
- Use PHP 8.4 asymmetric visibility (`public private(set)`)
- Have comprehensive error messages

### 1.4 API Integration with Union Types

**Approach:** Accept both value objects and primitives for ergonomics, validate automatically.

**API Design:**

```php
// Ergonomic - accepts primitives
DockerContainer::create('nginx:latest')
    ->mapPort(8080, 80)
    ->name('my-container')
    ->bindMount('/host/path', '/container/path')
    ->namedVolume('data-vol', '/app/data')
    ->setEnvironmentVariable('KEY', 'value')
    ->network('my-network');

// Also accepts value objects if already created
$port = Port::from(8080);
$container->mapPort($port, 80);
```

**Method Signatures:**

```php
public function create(ImageName|string $image, bool $cleanOnDestruct = true): self
{
    $this->image = $image instanceof ImageName ? $image : ImageName::from($image);
    // ...
}

public function mapPort(Port|int $portOnHost, Port|int $portOnDocker): self
{
    $hostPort = $portOnHost instanceof Port ? $portOnHost : Port::from($portOnHost);
    $dockerPort = $portOnDocker instanceof Port ? $portOnDocker : Port::from($portOnDocker);

    $this->_portMappings[] = new PortMapping($hostPort, $dockerPort);
    return $this;
}

public function name(ContainerName|string $name): self
{
    $this->name = $name instanceof ContainerName ? $name : ContainerName::from($name);
    return $this;
}

public function bindMount(HostPath|string $source, ContainerPath|string $target, string $flags = ''): self
{
    $sourcePath = $source instanceof HostPath ? $source : HostPath::from($source);
    $targetPath = $target instanceof ContainerPath ? $target : ContainerPath::from($target);

    $this->_bindMounts[] = new BindMountMapping($sourcePath, $targetPath, $flags);
    return $this;
}

public function namedVolume(VolumeName|string $name, ContainerPath|string $target, string $flags = ''): self
{
    $volumeName = $name instanceof VolumeName ? $name : VolumeName::from($name);
    $targetPath = $target instanceof ContainerPath ? $target : ContainerPath::from($target);

    $this->_namedVolumes[] = new NamedVolumeMapping($volumeName, $targetPath, $flags);
    return $this;
}
```

**Pattern:**
1. Accept union type (ValueObject|primitive)
2. Guard clause: if not value object, create from primitive (validates automatically)
3. Proceed with validated value object

### 1.5 Validation Rules

**Port:**
- Range: 1-65535
- Error: `"Port must be between 1 and 65535, got {value}"`

**ContainerName / NetworkName:**
- Pattern: `^[a-zA-Z0-9][a-zA-Z0-9_.-]*$`
- Min length: 1, Max length: 255
- Error: `"Container name must start with alphanumeric and contain only [a-zA-Z0-9_.-], got '{value}'"`

**ImageName:**
- Pattern: `^(?:(?<registry>[^/]+)/)?(?:(?<namespace>[^/]+)/)?(?<repository>[^:@]+)(?::(?<tag>[^@]+))?(?:@(?<digest>.+))?$`
- Error: `"Invalid image name format. Expected [registry/][namespace/]repository[:tag][@digest], got '{value}'"`

**HostPath:**
- Must be absolute (starts with `/`)
- Must exist: `file_exists($path)`
- Must be readable: `is_readable($path)`
- Error: `"Host path '{path}' does not exist or is not readable"`

**VolumeName:**
- Pattern: `^[a-zA-Z0-9][a-zA-Z0-9_.-]*$`
- Min length: 1, Max length: 255
- Error: `"Volume name must be alphanumeric with optional [_.-], got '{value}'"`

**ContainerPath:**
- Must be absolute (starts with `/`)
- No filesystem validation (path is inside container)
- Error: `"Container path must be absolute (start with /), got '{value}'"`

**EnvironmentVariable:**
- Key pattern: `^[a-zA-Z_][a-zA-Z0-9_]*$`
- Value: any string (including empty)
- Error: `"Environment variable key must start with letter/underscore and contain only [a-zA-Z0-9_], got '{key}'"`

**RemoteHost:**
- Validates: `tcp://host:port`, `ssh://user@host`, `unix:///path/to/socket`
- Uses `parse_url()` for validation
- Error: `"Invalid remote host format. Expected tcp://host:port, ssh://user@host, or unix:///socket, got '{value}'"`

### 1.6 Mapping Classes Refactoring

**Refactored to use Value Objects with Union Types:**

```php
final readonly class PortMapping {
    public Port $portOnHost;
    public Port $portOnDocker;

    public function __construct(
        Port|int $portOnHost,
        Port|int $portOnDocker
    ) {
        $this->portOnHost = $portOnHost instanceof Port ? $portOnHost : Port::from($portOnHost);
        $this->portOnDocker = $portOnDocker instanceof Port ? $portOnDocker : Port::from($portOnDocker);
    }

    public function __toString(): string {
        return "{$this->portOnHost->value}:{$this->portOnDocker->value}";
    }
}

final readonly class BindMountMapping {
    public HostPath $source;
    public ContainerPath $target;
    public string $flags;

    public function __construct(
        HostPath|string $source,
        ContainerPath|string $target,
        string $flags = ''
    ) {
        $this->source = $source instanceof HostPath ? $source : HostPath::from($source);
        $this->target = $target instanceof ContainerPath ? $target : ContainerPath::from($target);
        $this->flags = $flags;
    }

    public function __toString(): string {
        $mapping = "{$this->source}:{$this->target}";
        return $this->flags !== '' ? "{$mapping}:{$this->flags}" : $mapping;
    }
}

final readonly class NamedVolumeMapping {
    public VolumeName $name;
    public ContainerPath $target;
    public string $flags;

    public function __construct(
        VolumeName|string $name,
        ContainerPath|string $target,
        string $flags = ''
    ) {
        $this->name = $name instanceof VolumeName ? $name : VolumeName::from($name);
        $this->target = $target instanceof ContainerPath ? $target : ContainerPath::from($target);
        $this->flags = $flags;
    }

    public function __toString(): string {
        $mapping = "{$this->name}:{$this->target}";
        return $this->flags !== '' ? "{$mapping}:{$this->flags}" : $mapping;
    }
}
```

### 1.7 DockerContainerInstance Security

**Array-Based Execution:**

```php
public function execute(string $command): string
{
    $dockerCommand = array_merge(
        $this->getBaseCommand(),
        ['exec', '--interactive', $this->dockerIdentifier, $this->shell]
    );

    $process = new Process($dockerCommand);
    $process->setInput($command);  // Pass via stdin, not shell
    $process->setTimeout(null);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new \RuntimeException(
            "Command execution failed: {$process->getErrorOutput()}"
        );
    }

    return $process->getOutput();
}

private function getBaseCommand(): array
{
    if ($this->config->daemonHost !== null) {
        return ['docker', '-H', (string) $this->config->daemonHost];
    }

    return ['docker'];
}
```

**SSH Key Installation with Validation:**

```php
public function addPublicKey(
    HostPath|string $pathToPublicKey,
    ContainerPath|string $pathToAuthorizedKeys = self::DEFAULT_PATH_AUTHORIZED_KEYS
): self {
    $publicKeyPath = $pathToPublicKey instanceof HostPath
        ? $pathToPublicKey
        : HostPath::from($pathToPublicKey);

    $authorizedKeysPath = $pathToAuthorizedKeys instanceof ContainerPath
        ? $pathToAuthorizedKeys
        : ContainerPath::from($pathToAuthorizedKeys);

    $publicKeyContents = trim(file_get_contents((string) $publicKeyPath));
    $sshDir = dirname((string) $authorizedKeysPath);

    // Paths validated by value objects - safe to use
    $this->execute("mkdir -p {$sshDir}");
    $this->execute("chmod 700 {$sshDir}");
    $this->execute("echo '{$publicKeyContents}' >> {$authorizedKeysPath}");
    $this->execute("chmod 600 {$authorizedKeysPath}");
    $this->execute("chown root:root {$authorizedKeysPath}");

    return $this;
}
```

### 1.8 Breaking Changes and Migration

**Breaking Changes in 3.0.0:**

1. **Command methods return arrays instead of strings**
2. **setVolume() removed**, replaced with `bindMount()` and `namedVolume()`
3. **Validation now throws on invalid input** (was accepted, failed at Docker runtime)
4. **EnvironmentVariable** structure changed

**Migration Path:**

Most code continues to work with minimal changes:

```php
// Before (2.x)
$container->setVolume('/host/data', '/container/data');

// After (3.0.0)
$container->bindMount('/host/data', '/container/data');
```

**Create UPGRADE.md:**

Document all breaking changes with before/after examples and rationale.

---

## 2. Test Coverage Improvements

### 2.1 Current State

**Coverage Analysis:**
- **Overall:** 88% (target: 95%+)
- **DockerContainer.php:** 97.6% (excellent)
- **DockerContainerInstance.php:** 66.3% (needs work)
- **Mapping classes:** 100% (excellent)
- **Exception classes:** 100% (excellent)

**Gap Analysis:**

Missing coverage in DockerContainerInstance:
- `fromExisting()` - Factory for existing containers (0%)
- `isRunning()` - Status check (0%)
- `start()` / `stop()` with `async=true` parameter
- Error paths in `addPublicKey()`, `addFiles()`, `inspect()`
- Network-related operations

### 2.2 Testing Strategy: Hybrid Approach

**Unit Tests** - Fast, isolated, no Docker required:
- Command generation (verify arrays)
- Value object validation (all edge cases)
- Mapping class construction
- Error handling (exception types and messages)
- Configuration state management

**Integration Tests** - Real Docker, comprehensive:
- Container lifecycle (start, stop, status)
- `fromExisting()` with real running containers
- SSH key installation end-to-end
- File operations
- Network operations
- Async operations

**Test Organization:**

```
tests/
├── Unit/
│   ├── ValueObjects/
│   │   ├── PortTest.php
│   │   ├── ContainerNameTest.php
│   │   ├── ImageNameTest.php
│   │   ├── HostPathTest.php
│   │   ├── VolumeNameTest.php
│   │   ├── ContainerPathTest.php
│   │   ├── EnvironmentVariableTest.php
│   │   └── RemoteHostTest.php
│   ├── Mappings/
│   │   ├── PortMappingTest.php
│   │   ├── BindMountMappingTest.php
│   │   ├── NamedVolumeMappingTest.php
│   │   ├── EnvironmentMappingTest.php
│   │   └── LabelMappingTest.php
│   ├── CommandGenerationTest.php
│   └── ValidationTest.php
├── Integration/
│   ├── ContainerLifecycleTest.php
│   ├── ContainerInstanceTest.php
│   ├── NetworkingTest.php
│   ├── FileOperationsTest.php
│   └── SecurityTest.php
├── Fixtures/
│   ├── SharedContainer.php
│   └── test_public_key.pub
└── Pest.php
```

### 2.3 Shared Container Fixtures

**Approach:** Mix of shared and ephemeral containers.

- **Shared fixture:** Read-only tests (inspect, isRunning) use single container
- **Ephemeral:** State-modifying tests (addPublicKey, addFiles) create/destroy per test

**Implementation (Pest hooks):**

```php
// tests/Pest.php
beforeAll(function () {
    // Start shared container for read-only tests
    $this->sharedContainer = DockerContainer::create('nginx:alpine')
        ->name('test-shared-container')
        ->start();
});

afterAll(function () {
    // Cleanup shared container
    $this->sharedContainer->stop();
});
```

### 2.4 Testing Async Operations

**Approach:** Condition-based polling with timeout.

```php
it('starts container asynchronously', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-async-container');

    $instance = $container->start(async: true);

    // Poll until running or timeout
    $timeout = 10; // seconds
    $start = time();

    while (!$instance->isRunning()) {
        if (time() - $start > $timeout) {
            throw new \RuntimeException('Container failed to start within timeout');
        }
        usleep(100000); // 100ms
    }

    expect($instance->isRunning())->toBeTrue();

    $instance->stop();
});
```

### 2.5 Security Testing

**Command Injection Prevention:**

```php
// tests/Integration/SecurityTest.php
it('prevents command injection in container names', function () {
    expect(fn() => DockerContainer::create('nginx')
        ->name('test; rm -rf /; #'))
        ->toThrow(InvalidArgumentException::class);
});

it('prevents command injection in network names', function () {
    expect(fn() => DockerContainer::create('nginx')
        ->network('$(malicious_command)'))
        ->toThrow(InvalidArgumentException::class);
});

it('handles special characters safely in environment variables', function () {
    $container = DockerContainer::create('nginx')
        ->setEnvironmentVariable('KEY', 'value with $pecial chars!@#');

    $command = $container->getStartCommand();

    expect($command)->toBeArray()
        ->and($command)->toContain('-e')
        ->and($command)->toContain('KEY=value with $pecial chars!@#');
});
```

### 2.6 Value Object Testing

**Comprehensive edge case coverage:**

```php
// tests/Unit/ValueObjects/PortTest.php
it('accepts valid ports', function (int $port) {
    $portVO = Port::from($port);
    expect($portVO->value)->toBe($port);
})->with([1, 80, 443, 8080, 65535]);

it('rejects invalid ports', function (int $invalid) {
    expect(fn() => Port::from($invalid))
        ->toThrow(InvalidArgumentException::class);
})->with([0, -1, 65536, 99999]);

it('converts to string correctly', function () {
    $port = Port::from(8080);
    expect((string) $port)->toBe('8080');
});

// tests/Unit/ValueObjects/ContainerNameTest.php
it('validates container names follow Docker rules', function (string $valid) {
    $name = ContainerName::from($valid);
    expect($name->value)->toBe($valid);
})->with([
    'valid-name',
    'container_123',
    'my.container',
    'test-123_abc.xyz',
]);

it('rejects invalid container names', function (string $invalid) {
    expect(fn() => ContainerName::from($invalid))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'INVALID NAME',  // spaces
    'test@container',  // @ not allowed
    '-starts-with-dash',  // must start with alphanumeric
    '',  // empty
    str_repeat('a', 256),  // too long
]);
```

### 2.7 Error Path Coverage

**Mix: Integration for happy path, unit for error paths:**

```php
// Integration - Happy path
it('adds public key successfully', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('ssh-test-container')
        ->start();

    $container->addPublicKey(__DIR__ . '/../Fixtures/test_public_key.pub');

    // Verify key was added
    $output = $container->execute('cat /root/.ssh/authorized_keys');
    expect($output)->toContain('ssh-rsa');

    $container->stop();
});

// Unit - Error paths
it('throws when public key file does not exist', function () {
    expect(fn() => HostPath::from('/nonexistent/key.pub'))
        ->toThrow(InvalidArgumentException::class, 'does not exist');
});

it('throws when public key file is not readable', function () {
    // Create unreadable file
    $tempFile = tempnam(sys_get_temp_dir(), 'key');
    chmod($tempFile, 0000);

    expect(fn() => HostPath::from($tempFile))
        ->toThrow(InvalidArgumentException::class, 'not readable');

    chmod($tempFile, 0644);
    unlink($tempFile);
});
```

### 2.8 Coverage Enforcement

**Dual Enforcement:**

1. **Local (PHPUnit):** Fail builds if coverage < 95%
2. **Remote (Coveralls):** Block PRs if coverage drops

**phpunit.xml configuration:**

```xml
<coverage>
    <report>
        <clover outputFile="build/logs/clover.xml"/>
        <html outputDirectory="build/coverage"/>
    </report>
    <check>
        <coverage>
            <minCoverage>95</minCoverage>
        </coverage>
    </check>
</coverage>
```

**GitHub Actions:**
- Existing Coveralls integration continues
- Add status check requirement in repository settings

### 2.9 Coverage Goals

**Per-Component Targets:**

- Value Objects: 100% (critical and simple)
- Mapping Classes: 100% (already achieved)
- DockerContainer: 98%+ (close gaps from 97.6%)
- DockerContainerInstance: 95%+ (major improvement from 66.3%)
- Exceptions: 100% (already achieved)
- **Overall Project: 95%+** (from 88%)

---

## 3. Type Safety Improvements

### 3.1 Array Encapsulation

**Problem:** Public array properties can be mutated incorrectly:

```php
// Current (vulnerable to incorrect mutation)
public array $portMappings = [];

// External code can break type safety
$container->portMappings[] = "not a PortMapping";
```

**Solution:** Private arrays with public read-only hooks.

```php
private array $_portMappings = [];
private array $_bindMounts = [];
private array $_namedVolumes = [];
private array $_environmentMappings = [];

/** @return list<PortMapping> */
public array $portMappings {
    get => array_values($this->_portMappings);
}

/** @return list<BindMountMapping> */
public array $bindMounts {
    get => array_values($this->_bindMounts);
}

/** @return list<NamedVolumeMapping> */
public array $namedVolumes {
    get => array_values($this->_namedVolumes);
}

/** @return list<EnvironmentMapping> */
public array $environmentMappings {
    get => array_values($this->_environmentMappings);
}
```

**Benefits:**
- Read-only access via property hooks
- `array_values()` guarantees `list<T>` (0-indexed, no gaps)
- Setter methods continue to modify backing properties
- Type safety enforced at runtime

**Exposed Properties:**
- `portMappings` - Common to inspect
- `bindMounts` / `namedVolumes` - Useful for debugging
- `environmentMappings` - Common to inspect

Not exposed (internal):
- `commands` - Less common, more internal
- `optionalArgs` - Internal implementation detail

### 3.2 PHP 8.4 Property Hooks

**Value Objects with Asymmetric Visibility:**

```php
final readonly class Port {
    /** @var positive-int */
    public private(set) int $value;

    public function __construct(int $value) {
        if ($value < 1 || $value > 65535) {
            throw new \InvalidArgumentException(
                "Port must be between 1 and 65535, got {$value}"
            );
        }
        $this->value = $value;
    }

    public static function from(int $value): self {
        return new self($value);
    }

    public function __toString(): string {
        return (string) $this->value;
    }
}
```

**Pattern applied to ALL value objects:**
- Port, ContainerName, NetworkName, ImageName
- HostPath, VolumeName, ContainerPath
- EnvironmentVariable, RemoteHost

**Benefits:**
- `$port->value` readable publicly
- Only constructor can assign (write)
- Cleaner than `getValue()` methods
- Type-safe by design

**DockerContainer with Property Hooks:**

```php
class DockerContainer {
    // Backing properties (private)
    private array $_portMappings = [];
    private array $_bindMounts = [];

    // Public read-only hooks
    /** @return list<PortMapping> */
    public array $portMappings {
        get => array_values($this->_portMappings);
    }

    /** @return list<BindMountMapping> */
    public array $bindMounts {
        get => array_values($this->_bindMounts);
    }

    // Setter methods modify backing properties
    public function mapPort(Port|int $portOnHost, Port|int $portOnDocker): self {
        $hostPort = $portOnHost instanceof Port ? $portOnHost : Port::from($portOnHost);
        $dockerPort = $portOnDocker instanceof Port ? $portOnDocker : Port::from($portOnDocker);

        $this->_portMappings[] = new PortMapping($hostPort, $dockerPort);
        return $this;
    }
}
```

### 3.3 Precise Generic Types

**Selective refinement:** Ultra-precise types in value objects, pragmatic in application code.

**Value Objects (ultra-precise):**

```php
final readonly class Port {
    /** @var positive-int */  // PHPStan knows this is > 0
    public private(set) int $value;
}

final readonly class ContainerName {
    /** @var non-empty-string */  // PHPStan knows this is not ""
    public private(set) string $value;
}
```

**DockerContainer (pragmatic):**

```php
/** @var list<PortMapping> */  // Not non-empty-list, can be empty
private array $_portMappings = [];

/** @var list<string> */  // Not non-empty-string, commands can be empty
private array $commands = [];
```

**Rationale:**
- Value objects validate anyway, precise types help PHPStan
- Application logic doesn't require non-empty constraints

### 3.4 Null Safety

**Current state is correct:** No changes needed.

```php
public ?ContainerName $name = null;
public ?NetworkName $network = null;
public ?RemoteHost $daemonHost = null;
```

- Nullable types are explicit
- No implicit nulls
- Clear intent in API

---

## 4. Implementation Plan

### 4.1 Phase 1: Value Objects and Validation

**Tasks:**
1. Create `src/ValueObjects/` directory
2. Implement all 9 value objects with validation
3. Add comprehensive unit tests (100% coverage target)
4. Verify PHPStan level 10 compliance

**Deliverables:**
- Port, ContainerName, NetworkName, ImageName
- HostPath, VolumeName, ContainerPath
- EnvironmentVariable, RemoteHost
- Full test suite for value objects

### 4.2 Phase 2: Mapping Classes Refactoring

**Tasks:**
1. Refactor PortMapping to accept Port value objects
2. Split VolumeMapping into BindMountMapping and NamedVolumeMapping
3. Refactor EnvironmentMapping to use EnvironmentVariable
4. Update all unit tests
5. Maintain 100% coverage

**Deliverables:**
- Refactored mapping classes
- Updated tests
- PHPStan level 10 passing

### 4.3 Phase 3: DockerContainer Refactoring

**Tasks:**
1. Update method signatures to accept union types (VO|primitive)
2. Add guard clauses to create VOs from primitives
3. Replace `setVolume()` with `bindMount()` and `namedVolume()`
4. Refactor command generation to array-based
5. Encapsulate public arrays with property hooks
6. Update tests

**Deliverables:**
- Refactored DockerContainer with breaking changes
- Array-based command generation
- Property hooks for array access
- 98%+ test coverage

### 4.4 Phase 4: DockerContainerInstance Refactoring

**Tasks:**
1. Refactor command execution to array-based
2. Update `addPublicKey()` to use value objects
3. Update `addFiles()` to use value objects
4. Add missing tests for `fromExisting()`, `isRunning()`, async operations
5. Add error path coverage

**Deliverables:**
- Secure command execution
- 95%+ test coverage (from 66.3%)
- All security vulnerabilities addressed

### 4.5 Phase 5: Testing and Quality Assurance

**Tasks:**
1. Add integration tests for all critical operations
2. Add security tests (command injection prevention)
3. Implement polling-based async tests
4. Configure coverage enforcement (PHPUnit + Coveralls)
5. Verify overall coverage 95%+

**Deliverables:**
- Comprehensive test suite
- Security test coverage
- Coverage enforcement in CI
- 95%+ overall coverage

### 4.6 Phase 6: Documentation and Migration

**Tasks:**
1. Create UPGRADE.md with migration guide
2. Update README.md with new API examples
3. Update CHANGELOG.md for 3.0.0
4. Add PHPDoc for all new public methods
5. Create release notes

**Deliverables:**
- Complete documentation
- Migration guide
- Release notes for 3.0.0

---

## 5. Breaking Changes Summary

### 5.1 API Changes

**Removed:**
- `DockerContainer::setVolume()` - Use `bindMount()` or `namedVolume()`

**Changed Return Types:**
- `getStartCommand(): string` → `getStartCommand(): array`
- `getStopCommand(): string` → `getStopCommand(): array`
- `getExecCommand(): string` → `getExecCommand(): array`

**New Validation:**
- All inputs now validated at setter time
- Invalid input throws `InvalidArgumentException` instead of failing at Docker runtime

**New Methods:**
- `bindMount(HostPath|string $source, ContainerPath|string $target, string $flags = ''): self`
- `namedVolume(VolumeName|string $name, ContainerPath|string $target, string $flags = ''): self`

### 5.2 Internal Changes

**Mapping Classes:**
- `VolumeMapping` split into `BindMountMapping` and `NamedVolumeMapping`
- All mappings now accept value objects or primitives

**Property Visibility:**
- Public arrays now private with read-only hooks
- Access via property hooks: `$container->portMappings` (read-only)

### 5.3 Version Bump

**2.x → 3.0.0**

Semantic versioning major bump due to breaking changes for security improvements.

---

## 6. Success Criteria

### 6.1 Security

- [ ] Zero command injection vulnerabilities
- [ ] All user input validated
- [ ] Security tests pass (injection prevention)
- [ ] PHPStan level 10 compliance maintained

### 6.2 Test Coverage

- [ ] Overall coverage ≥ 95%
- [ ] DockerContainerInstance coverage ≥ 95% (from 66.3%)
- [ ] Value objects coverage = 100%
- [ ] Mapping classes coverage = 100%
- [ ] Integration tests for all critical operations
- [ ] Coverage enforcement in CI (PHPUnit + Coveralls)

### 6.3 Type Safety

- [ ] All public arrays encapsulated with property hooks
- [ ] PHP 8.4 asymmetric visibility in all value objects
- [ ] Precise PHPStan types in value objects
- [ ] PHPStan level 10 passing with zero errors

### 6.4 Code Quality

- [ ] PER code style (php-cs-fixer)
- [ ] All files have `declare(strict_types=1)`
- [ ] All files have ABOUTME comments
- [ ] Comprehensive PHPDoc

### 6.5 Documentation

- [ ] UPGRADE.md created with migration examples
- [ ] README.md updated with new API
- [ ] CHANGELOG.md for 3.0.0
- [ ] Release notes published

---

## 7. Risks and Mitigations

### 7.1 Risk: Breaking Changes Impact

**Impact:** Users must update code to migrate to 3.0.0

**Mitigation:**
- Comprehensive UPGRADE.md with examples
- Most changes are drop-in (primitives still accepted)
- Only `setVolume()` requires code changes
- Clear communication in release notes

### 7.2 Risk: Test Suite Complexity

**Impact:** Integration tests may be slow, flaky

**Mitigation:**
- Shared fixtures for read-only tests
- Condition-based polling (not sleep) for async
- Clear separation of unit vs integration
- Parallel test execution where possible

### 7.3 Risk: PHP 8.4 Adoption

**Impact:** PHP 8.4 is very new (released Nov 2024)

**Mitigation:**
- Already committed to PHP 8.4 requirement
- Features used are stable (property hooks, asymmetric visibility)
- CI tests on PHP 8.4

### 7.4 Risk: Over-Engineering

**Impact:** Too many value objects, complex API

**Mitigation:**
- Union types keep API ergonomic (primitives accepted)
- Value objects internal, users rarely see them
- Each VO has clear security/validation purpose
- YAGNI applied (only necessary VOs created)

---

## 8. Conclusion

This design achieves three critical improvements:

1. **Security Hardening** - Eliminates command injection through array-based commands and strict validation
2. **Test Coverage** - Increases from 88% to 95%+ with hybrid testing strategy
3. **Type Safety** - Modernizes with PHP 8.4 features and stronger encapsulation

The implementation follows SOLID principles, maintains backward compatibility where possible (union types), and provides a clear migration path for breaking changes.

**Next Steps:**
1. Review and approve this design document
2. Create implementation plan with detailed tasks
3. Set up git worktree for isolated development
4. Begin Phase 1: Value Objects implementation

---

**Approved by:** [Pending]
**Implementation Start:** [TBD]
