<?php

// ABOUTME: Tests for DockerContainerInstance execution and timeout configuration.
// ABOUTME: Validates command execution behavior and process timeout handling.

declare(strict_types=1);

use Ninja\Docker\DockerContainer;
use Ninja\Docker\DockerContainerInstance;

beforeEach(function () {
    $this->containerInstance = new DockerContainerInstance(DockerContainer::create('spatie/docker'), '1234', 'test');
});

it('executes commands and returns string output', function () {
    $output = $this->containerInstance->execute('echo "test"');

    expect($output)->toBeString();
})->throws(\RuntimeException::class);

it('can get the container name', function () {
    expect($this->containerInstance->getName())->toEqual('test');
});

it('can get the docker identifier', function () {
    expect($this->containerInstance->getDockerIdentifier())->toEqual('1234');
});

it('can get the short docker identifier', function () {
    expect($this->containerInstance->getShortDockerIdentifier())->toEqual('1234');
});

it('can get the config', function () {
    $config = $this->containerInstance->getConfig();

    expect($config)->toBeInstanceOf(DockerContainer::class)
        ->and($config->image)->toEqual('spatie/docker');
});


it('validates public key path exists', function () {
    expect(fn() => $this->containerInstance->addPublicKey('/nonexistent/key.pub'))
        ->toThrow(\InvalidArgumentException::class, 'does not exist');
});

it('isRunning returns false for non-existent container', function () {
    $isRunning = DockerContainerInstance::isRunning('nonexistent_container_' . uniqid());

    expect($isRunning)->toBeFalse();
});

it('can stop a container', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-stop-' . uniqid())
        ->doNotCleanUpAfterExit();

    $instance = $container->start();

    $process = $instance->stop();

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class)
        ->and($process->isSuccessful())->toBeTrue();
})->group('integration');

it('can stop a container asynchronously', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-stop-async-' . uniqid())
        ->doNotCleanUpAfterExit();

    $instance = $container->start();

    $process = $instance->stop(true);

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class)
        ->and($process->isStarted())->toBeTrue();

    $process->wait();
})->group('integration');

it('can execute commands in a running container', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-execute-' . uniqid())
        ->shell('sh');

    $instance = $container->start();

    $output = $instance->execute('echo "hello world"');

    expect($output)->toBeString()
        ->and($output)->toContain('hello world');
})->group('integration');

it('can add public key to container', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-pubkey-' . uniqid())
        ->shell('sh');

    $instance = $container->start();

    $tempKeyFile = tempnam(sys_get_temp_dir(), 'pubkey');
    file_put_contents($tempKeyFile, 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDTest test@example.com');

    $instance->execute('mkdir -p /root/.ssh');

    $result = $instance->addPublicKey($tempKeyFile);

    expect($result)->toBe($instance);

    $output = $instance->execute('cat /root/.ssh/authorized_keys');
    expect($output)->toContain('ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDTest');

    unlink($tempKeyFile);
})->group('integration');

it('can add public key with value objects', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-pubkey-vo-' . uniqid())
        ->shell('sh');

    $instance = $container->start();

    $tempKeyFile = tempnam(sys_get_temp_dir(), 'pubkey');
    file_put_contents($tempKeyFile, 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDTestVO test@example.com');

    $instance->execute('mkdir -p /custom/.ssh');

    $hostPath = \Ninja\Docker\ValueObjects\HostPath::from($tempKeyFile);
    $containerPath = \Ninja\Docker\ValueObjects\ContainerPath::from('/custom/.ssh/authorized_keys');

    $result = $instance->addPublicKey($hostPath, $containerPath);

    expect($result)->toBe($instance);

    $output = $instance->execute('cat /custom/.ssh/authorized_keys');
    expect($output)->toContain('ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDTestVO');

    unlink($tempKeyFile);
})->group('integration');

it('can create instance from existing container', function () {
    $containerName = 'test-existing-' . uniqid();

    $container = DockerContainer::create('nginx:alpine')
        ->name($containerName)
        ->doNotCleanUpAfterExit();

    $originalInstance = $container->start();

    $instance = DockerContainerInstance::fromExisting($containerName);

    expect($instance)->toBeInstanceOf(DockerContainerInstance::class)
        ->and($instance->getName())->toBe($containerName)
        ->and($instance->getDockerIdentifier())->not()->toBeEmpty();

    $originalInstance->stop();
})->group('integration');

it('isRunning returns true for running container', function () {
    $containerName = 'test-running-' . uniqid();

    $container = DockerContainer::create('nginx:alpine')
        ->name($containerName);

    $instance = $container->start();

    $isRunning = DockerContainerInstance::isRunning($containerName);

    expect($isRunning)->toBeTrue();
})->group('integration');

it('can start a stopped container', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-start-stopped-' . uniqid())
        ->doNotCleanUpAfterExit();

    $instance = $container->start();
    $instance->stop();

    $process = $instance->start();

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class)
        ->and($process->isSuccessful())->toBeTrue();
})->group('integration');

it('can start a container asynchronously', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-start-async-' . uniqid())
        ->doNotCleanUpAfterExit();

    $instance = $container->start();
    $instance->stop();

    $process = $instance->start(true);

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class)
        ->and($process->isStarted())->toBeTrue();

    $process->wait();
})->group('integration');

it('throws exception when addFiles fails', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-addfiles-fail-' . uniqid());

    $instance = $container->start();

    expect(fn() => $instance->addFiles('/nonexistent/file.txt', '/tmp'))
        ->toThrow(\Symfony\Component\Process\Exception\ProcessFailedException::class);
})->group('integration');
