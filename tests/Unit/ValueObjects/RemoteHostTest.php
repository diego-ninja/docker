<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\RemoteHost;

it('accepts valid remote host formats', function (string $valid) {
    $host = RemoteHost::from($valid);
    expect($host->value)->toBe($valid);
})->with([
    'tcp://192.168.1.1:2375',
    'tcp://docker.example.com:2376',
    'ssh://user@remote-host',
    'ssh://root@192.168.1.1:22',
    'unix:///var/run/docker.sock',
]);

it('rejects invalid remote host formats', function (string $invalid) {
    expect(fn() => RemoteHost::from($invalid))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    '',
    'invalid-format',
    'http://wrong-protocol',
    'tcp://;injection',
]);

it('rejects unix socket with relative path', function () {
    expect(fn() => RemoteHost::from('unix://relative/path'))
        ->toThrow(\InvalidArgumentException::class, 'Unix socket must be an absolute path');
});

it('rejects tcp without host', function () {
    expect(fn() => RemoteHost::from('tcp://'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid remote host format');
});

it('rejects ssh without host', function () {
    expect(fn() => RemoteHost::from('ssh://'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid remote host format');
});

it('converts to string correctly', function () {
    $host = RemoteHost::from('tcp://192.168.1.1:2375');
    expect((string) $host)->toBe('tcp://192.168.1.1:2375');
});
