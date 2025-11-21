<?php

declare(strict_types=1);

use Ninja\Docker\ValueObjects\ImageName;

it('validates simple image names', function (string $valid) {
    $image = ImageName::from($valid);
    expect($image->value)->toBe($valid);
})->with([
    'nginx',
    'ubuntu',
    'alpine',
]);

it('validates image names with tags', function (string $valid) {
    $image = ImageName::from($valid);
    expect($image->value)->toBe($valid);
})->with([
    'nginx:latest',
    'nginx:1.21',
    'ubuntu:22.04',
    'alpine:3.18.0',
]);

it('validates image names with registry', function (string $valid) {
    $image = ImageName::from($valid);
    expect($image->value)->toBe($valid);
})->with([
    'registry.io/nginx',
    'docker.io/library/nginx',
    'ghcr.io/owner/repo:tag',
]);

it('validates image names with digest', function (string $valid) {
    $image = ImageName::from($valid);
    expect($image->value)->toBe($valid);
})->with([
    'nginx@sha256:abcd1234',
    'nginx:latest@sha256:abcd1234',
]);

it('rejects invalid image names', function (string $invalid) {
    expect(fn() => ImageName::from($invalid))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    '',
    'UPPERCASE',
    'image with spaces',
    'image@@@invalid',
    'test;injection',
]);

it('converts to string correctly', function () {
    $image = ImageName::from('nginx:latest');
    expect((string)$image)->toBe('nginx:latest');
});
