<?php

// ABOUTME: Tests for DockerContainerInstance execution and timeout configuration.
// ABOUTME: Validates command execution behavior and process timeout handling.

declare(strict_types=1);

use Ninja\Docker\DockerContainer;
use Ninja\Docker\DockerContainerInstance;

beforeEach(function () {
    $this->containerInstance = new DockerContainerInstance(new DockerContainer('spatie/docker'), '1234', 'test');
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
