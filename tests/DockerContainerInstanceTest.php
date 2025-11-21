<?php

// ABOUTME: Tests for DockerContainerInstance execution and timeout configuration.
// ABOUTME: Validates command execution behavior and process timeout handling.

declare(strict_types=1);

use Ninja\Docker\DockerContainer;
use Ninja\Docker\DockerContainerInstance;

beforeEach(function () {
    $this->containerInstance = new DockerContainerInstance(DockerContainer::create('spatie/docker'), '1234', 'test');
});

it('defaults process timeout to 60s', function () {
    $process = $this->containerInstance->execute('whoami', false);

    expect($process->getTimeout())->toEqual(60);
});

it('can set a custom process timeout', function () {
    $process = $this->containerInstance->execute('whoami', false, 3600);

    expect($process->getTimeout())->toEqual(3600);
});

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

it('can execute array commands', function () {
    $process = $this->containerInstance->execute(['whoami', 'pwd'], false);

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class);
});

it('can execute string commands', function () {
    $process = $this->containerInstance->execute('whoami', false);

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class);
});

it('executes async commands', function () {
    $process = $this->containerInstance->execute('whoami', true);

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class);
});

it('throws exception when public key file does not exist', function () {
    $this->containerInstance->addPublicKey('/nonexistent/key.pub');
})->throws(\RuntimeException::class, 'Could not read contents of public key');

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

    $process = $instance->execute('echo "hello world"');

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class)
        ->and($process->isSuccessful())->toBeTrue()
        ->and($process->getOutput())->toContain('hello world');
})->group('integration');

it('can execute commands asynchronously', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('test-execute-async-' . uniqid())
        ->shell('sh');

    $instance = $container->start();

    $process = $instance->execute('echo "async test"', true);

    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class)
        ->and($process->isStarted())->toBeTrue();

    $process->wait();

    expect($process->isSuccessful())->toBeTrue()
        ->and($process->getOutput())->toContain('async test');
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

    $checkProcess = $instance->execute('cat /root/.ssh/authorized_keys');
    expect($checkProcess->getOutput())->toContain('ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDTest');

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
