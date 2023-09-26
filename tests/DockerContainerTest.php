<?php

declare(strict_types=1);

use Ninja\Docker\DockerContainer;

beforeEach(function () {
    $this->container = new DockerContainer('ninja/docker');
});

it('will daemonize and clean up the container by default', function () {
    $command = $this->container->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -d --rm ninja/docker');
});

it('can instantiate via the create method', function () {
    expect(DockerContainer::create('ninja/docker'))->toBeInstanceOf(DockerContainer::class);
});

it('can not be daemonized', function () {
    $command = $this->container
        ->doNotDaemonize()
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run --rm ninja/docker');
});

it('can be privileged', function () {
    $command = $this->container
        ->privileged()
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -d --privileged --rm ninja/docker');
});

it('can not be cleaned up', function () {
    $command = $this->container
        ->doNotCleanUpAfterExit()
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -d ninja/docker');
});

it('can be named', function () {
    $command = $this->container
        ->name('my-name')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run --name my-name -d --rm ninja/docker');
});

it('can map ports', function () {
    $command = $this->container
        ->mapPort(4848, 22)
        ->mapPort(9000, 21)
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -p 4848:22 -p 9000:21 -d --rm ninja/docker');
});

it('can map string ports', function () {
    $command = $this->container
        ->mapPort('127.0.0.1:4848', 22)
        ->mapPort('0.0.0.0:9000', 21)
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -p 127.0.0.1:4848:22 -p 0.0.0.0:9000:21 -d --rm ninja/docker');
});

it('can set environment variables', function () {
    $command = $this->container
        ->setEnvironmentVariable('NAME', 'VALUE')
        ->setEnvironmentVariable('NAME2', 'VALUE2')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -e NAME=VALUE -e NAME2=VALUE2 -d --rm ninja/docker');
});

it('can set volumes', function () {
    $command = $this->container
        ->setVolume('/on/my/host', '/on/my/container')
        ->setVolume('/data', '/data')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -v /on/my/host:/on/my/container -v /data:/data -d --rm ninja/docker');
});

it('can set labels', function () {
    $command = $this->container
        ->setLabel('traefik.enable', 'true')
        ->setLabel('foo', 'bar')
        ->setLabel('name', 'spatie')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -l traefik.enable=true -l foo=bar -l name=spatie -d --rm ninja/docker');
});

it('can set optional args', function () {
    $command = $this->container
        ->setOptionalArgs('-it', '-a', '-i', '-t')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -it -a -i -t -d --rm ninja/docker');
});

it('can set commands', function () {
    $command = $this->container
        ->setCommands('--api.insecure=true', '--entrypoints.web.address=:80')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -d --rm ninja/docker --api.insecure=true --entrypoints.web.address=:80');
});

it('can set network', function () {
    $command = $this->container
        ->network('my-network')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -d --rm --network my-network ninja/docker');
});

it('can use remote docker host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker -H ssh://username@host run -d --rm ninja/docker');
});

it('can execute command at start', function () {
    $command = $this->container
        ->command('whoami')
        ->getRunCommand("ninja/docker");

    expect($command)->toEqual('docker run -d --rm ninja/docker whoami');
});

it('can generate stop command', function () {
    $command = $this->container
        ->getStopCommand('ninja/docker');

    expect($command)->toEqual('docker stop ninja/docker');
});

it('can generate stop command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getStopCommand('ninja/docker');

    expect($command)->toEqual('docker -H ssh://username@host stop ninja/docker');
});

it('can generate exec command', function () {
    $command = $this->container
        ->getExecCommand('abcdefghijkl', 'whoami');

    expect($command)->toEqual('echo "whoami" | docker exec --interactive abcdefghijkl bash -');
});

it('can generate exec command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getExecCommand('abcdefghijkl', 'whoami');

    expect($command)->toEqual('echo "whoami" | docker -H ssh://username@host exec --interactive abcdefghijkl bash -');
});

it('can generate exec command with custom shell', function () {
    $command = $this->container
        ->shell('sh')
        ->getExecCommand('abcdefghijkl', 'whoami');

    expect($command)->toEqual('echo "whoami" | docker exec --interactive abcdefghijkl sh -');
});

it('can generate copy command', function () {
    $command = $this->container
        ->getCopyCommand('abcdefghijkl', '/home/spatie', '/mnt/spatie');

    expect($command)->toEqual('docker cp /home/spatie abcdefghijkl:/mnt/spatie');
});

it('can generate copy command with remote host', function () {
    $command = $this->container
        ->remoteHost('ssh://username@host')
        ->getCopyCommand('abcdefghijkl', '/home/spatie', '/mnt/spatie');

    expect($command)->toEqual('docker -H ssh://username@host cp /home/spatie abcdefghijkl:/mnt/spatie');
});

it('has a default start command timeout of 60s', function () {
    expect($this->container->getStartCommandTimeout())->toEqual(60);
});

it('can set a custom start command timeout', function () {
    $return = $this->container->setStartCommandTimeout(3600);

    expect($this->container->getStartCommandTimeout())->toEqual(3600);
    expect($return)->toEqual($this->container);
});
