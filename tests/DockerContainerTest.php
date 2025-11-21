<?php

// ABOUTME: Tests for DockerContainer configuration and command generation.
// ABOUTME: Validates fluent API and Docker CLI command building.

declare(strict_types=1);

use Ninja\Docker\DockerContainer;

beforeEach(function () {
    $this->container = DockerContainer::create('ninja/docker');
});

it('will daemonize and clean up the container by default', function () {
    $command = $this->container->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-d', '--rm', 'ninja/docker']);
});

it('can instantiate via the create method', function () {
    expect(DockerContainer::create('ninja/docker'))->toBeInstanceOf(DockerContainer::class);
});

it('can not be daemonized', function () {
    $command = $this->container
        ->doNotDaemonize()
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '--rm', 'ninja/docker']);
});

it('can be privileged', function () {
    $command = $this->container
        ->privileged()
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-d', '--privileged', '--rm', 'ninja/docker']);
});

it('can not be cleaned up', function () {
    $command = $this->container
        ->doNotCleanUpAfterExit()
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-d', 'ninja/docker']);
});

it('can be named', function () {
    $command = $this->container
        ->name('my-name')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '--name', 'my-name', '-d', '--rm', 'ninja/docker']);
});

it('can map ports', function () {
    $command = $this->container
        ->mapPort(4848, 22)
        ->mapPort(9000, 21)
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-p', '4848:22', '-p', '9000:21', '-d', '--rm', 'ninja/docker']);
});

it('can map string ports', function () {
    $command = $this->container
        ->mapPort('127.0.0.1:4848', 22)
        ->mapPort('0.0.0.0:9000', 21)
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-p', '127.0.0.1:4848:22', '-p', '0.0.0.0:9000:21', '-d', '--rm', 'ninja/docker']);
});

it('can set environment variables', function () {
    $command = $this->container
        ->setEnvironmentVariable('NAME', 'VALUE')
        ->setEnvironmentVariable('NAME2', 'VALUE2')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-e', 'NAME=VALUE', '-e', 'NAME2=VALUE2', '-d', '--rm', 'ninja/docker']);
});

it('can set volumes', function () {
    $command = $this->container
        ->setVolume('/on/my/host', '/on/my/container')
        ->setVolume('/data', '/data')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-v', '/on/my/host:/on/my/container', '-v', '/data:/data', '-d', '--rm', 'ninja/docker']);
});

it('can set labels', function () {
    $command = $this->container
        ->setLabel('traefik.enable', 'true')
        ->setLabel('foo', 'bar')
        ->setLabel('name', 'spatie')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-l', 'traefik.enable=true', '-l', 'foo=bar', '-l', 'name=spatie', '-d', '--rm', 'ninja/docker']);
});

it('can set optional args', function () {
    $command = $this->container
        ->setOptionalArgs('-it', '-a', '-i', '-t')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-it', '-a', '-i', '-t', '-d', '--rm', 'ninja/docker']);
});

it('can set commands', function () {
    $command = $this->container
        ->setCommands('--api.insecure=true', '--entrypoints.web.address=:80')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-d', '--rm', 'ninja/docker', '--api.insecure=true', '--entrypoints.web.address=:80']);
});

it('can set network', function () {
    $command = $this->container
        ->network('my-network')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-d', '--rm', '--network', 'my-network', 'ninja/docker']);
});

it('can use remote docker host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', '-H', 'ssh://username@host', 'run', '-d', '--rm', 'ninja/docker']);
});

it('can execute command at start', function () {
    $command = $this->container
        ->command('whoami')
        ->getRunCommand();

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'run', '-d', '--rm', 'ninja/docker', 'whoami']);
});

it('can generate stop command', function () {
    $command = $this->container
        ->getStopCommand('ninja/docker');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'stop', 'ninja/docker']);
});

it('can generate stop command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getStopCommand('ninja/docker');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', '-H', 'ssh://username@host', 'stop', 'ninja/docker']);
});

it('can generate exec command', function () {
    $command = $this->container
        ->getExecCommand('abcdefghijkl', 'whoami');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'exec', '--interactive', 'abcdefghijkl', 'bash', '-c', 'whoami']);
});

it('can generate exec command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getExecCommand('abcdefghijkl', 'whoami');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', '-H', 'ssh://username@host', 'exec', '--interactive', 'abcdefghijkl', 'bash', '-c', 'whoami']);
});

it('can generate exec command with custom shell', function () {
    $command = $this->container
        ->shell('sh')
        ->getExecCommand('abcdefghijkl', 'whoami');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'exec', '--interactive', 'abcdefghijkl', 'sh', '-c', 'whoami']);
});

it('can generate copy command', function () {
    $command = $this->container
        ->getCopyCommand('abcdefghijkl', '/home/spatie', '/mnt/spatie');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'cp', '/home/spatie', 'abcdefghijkl:/mnt/spatie']);
});

it('can generate copy command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getCopyCommand('abcdefghijkl', '/home/spatie', '/mnt/spatie');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', '-H', 'ssh://username@host', 'cp', '/home/spatie', 'abcdefghijkl:/mnt/spatie']);
});

it('has a default start command timeout of 60s', function () {
    expect($this->container->getStartCommandTimeout())->toEqual(60);
});

it('can set a custom start command timeout', function () {
    $return = $this->container->setStartCommandTimeout(3600);

    expect($this->container->getStartCommandTimeout())->toEqual(3600);
    expect($return)->toEqual($this->container);
});

it('can get inspect command', function () {
    $command = $this->container->getInspectCommand('abcdefghijkl');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'inspect', 'abcdefghijkl']);
});

it('can get inspect command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getInspectCommand('abcdefghijkl');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', '-H', 'ssh://username@host', 'inspect', 'abcdefghijkl']);
});

it('can change image', function () {
    $this->container->image('new-image');

    expect($this->container->image)->toEqual('new-image');
});

it('can toggle daemonize', function () {
    $this->container->daemonize(false);

    expect($this->container->daemonize)->toBeFalse();
});

it('can toggle clean up after exit', function () {
    $this->container->cleanUpAfterExit(false);

    expect($this->container->cleanUpAfterExit)->toBeFalse();
});

it('can toggle stop on destruct', function () {
    $this->container->stopOnDestruct(true);

    expect($this->container->stopOnDestruct)->toBeTrue();
});

it('can generate start command', function () {
    $command = $this->container->getStartCommand('abcdefghijkl');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', 'start', 'abcdefghijkl']);
});

it('can generate start command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getStartCommand('abcdefghijkl');

    expect($command)->toBeArray()
        ->and($command)->toEqual(['docker', '-H', 'ssh://username@host', 'start', 'abcdefghijkl']);
});

it('exposes port mappings as read-only array', function () {
    $container = DockerContainer::create('nginx')
        ->mapPort(8080, 80)
        ->mapPort(9090, 90);

    $mappings = $container->portMappings;

    expect($mappings)->toBeArray()
        ->and($mappings)->toHaveCount(2);
});

it('returns list type for port mappings', function () {
    $container = DockerContainer::create('nginx')
        ->mapPort(8080, 80);

    $mappings = $container->portMappings;

    expect(array_is_list($mappings))->toBeTrue();
});

it('exposes environment mappings as read-only array', function () {
    $container = DockerContainer::create('nginx')
        ->setEnvironmentVariable('KEY1', 'val1')
        ->setEnvironmentVariable('KEY2', 'val2');

    $mappings = $container->environmentMappings;

    expect($mappings)->toBeArray()
        ->and($mappings)->toHaveCount(2);
});

it('exposes volume mappings as read-only array', function () {
    $container = DockerContainer::create('nginx')
        ->setVolume('/host1', '/container1')
        ->setVolume('/host2', '/container2');

    $mappings = $container->volumeMappings;

    expect($mappings)->toBeArray()
        ->and($mappings)->toHaveCount(2);
});

it('accepts ImageName or string in create()', function () {
    $container1 = DockerContainer::create('nginx');
    $container2 = DockerContainer::create(\Ninja\Docker\ValueObjects\ImageName::from('nginx'));

    expect($container1->image)->toBeInstanceOf(\Ninja\Docker\ValueObjects\ImageName::class)
        ->and($container2->image)->toBeInstanceOf(\Ninja\Docker\ValueObjects\ImageName::class);
});

it('validates image name when string provided to create()', function () {
    expect(fn() => DockerContainer::create('INVALID IMAGE'))
        ->toThrow(\InvalidArgumentException::class);
});

it('accepts ContainerName or string in name()', function () {
    $container = DockerContainer::create('nginx')
        ->name('test-container');

    expect($container->name)->toBeInstanceOf(\Ninja\Docker\ValueObjects\ContainerName::class)
        ->and($container->name->value)->toBe('test-container');
});

it('validates container name when string provided to name()', function () {
    expect(fn() => DockerContainer::create('nginx')->name('invalid name'))
        ->toThrow(\InvalidArgumentException::class);
});

it('accepts NetworkName or string in network()', function () {
    $container = DockerContainer::create('nginx')
        ->network('my-network');

    expect($container->network)->toBeInstanceOf(\Ninja\Docker\ValueObjects\NetworkName::class)
        ->and($container->network->value)->toBe('my-network');
});

it('validates network name when string provided', function () {
    expect(fn() => DockerContainer::create('nginx')->network('invalid network'))
        ->toThrow(\InvalidArgumentException::class);
});

it('creates bind mount with primitives', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');

    $container = DockerContainer::create('nginx')
        ->bindMount($tempFile, '/app/data');

    expect($container->bindMounts)->toHaveCount(1)
        ->and($container->bindMounts[0])->toBeInstanceOf(\Ninja\Docker\BindMountMapping::class);

    unlink($tempFile);
});

it('creates named volume with primitives', function () {
    $container = DockerContainer::create('nginx')
        ->namedVolume('data-volume', '/app/data');

    expect($container->namedVolumes)->toHaveCount(1)
        ->and($container->namedVolumes[0])->toBeInstanceOf(\Ninja\Docker\NamedVolumeMapping::class);
});

it('validates bind mount paths', function () {
    expect(fn() => DockerContainer::create('nginx')
        ->bindMount('/nonexistent', '/app'))
        ->toThrow(\InvalidArgumentException::class, 'does not exist');
});

it('can start container with callback', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-callback-' . uniqid());

    $callbackInvoked = false;

    $instance = $container->start(function ($process) use (&$callbackInvoked) {
        $callbackInvoked = true;
    });

    expect($callbackInvoked)->toBeTrue()
        ->and($instance)->toBeInstanceOf(\Ninja\Docker\DockerContainerInstance::class);
})->group('integration');

it('can mix bind mounts and named volumes', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');

    $container = DockerContainer::create('nginx')
        ->bindMount($tempFile, '/app/data')
        ->namedVolume('test-volume', '/app/storage');

    expect($container->bindMounts)->toHaveCount(1)
        ->and($container->namedVolumes)->toHaveCount(1);

    unlink($tempFile);
});

it('generates command with both bind mounts and named volumes', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');

    $container = DockerContainer::create('nginx')
        ->bindMount($tempFile, '/app/data')
        ->namedVolume('test-volume', '/app/storage');

    $command = $container->getRunCommand();

    $commandString = implode(' ', $command);

    expect($commandString)->toContain('-v')
        ->and($commandString)->toContain($tempFile . ':/app/data')
        ->and($commandString)->toContain('test-volume:/app/storage');

    unlink($tempFile);
});
