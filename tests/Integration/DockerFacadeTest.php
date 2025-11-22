<?php

// ABOUTME: Integration tests for Docker facade with real containers.
// ABOUTME: Validates shortcuts work end-to-end with actual Docker daemon.

declare(strict_types=1);

use Ninja\Docker\Docker;
use Ninja\Docker\DockerContainerInstance;

it('starts nginx container and serves default page', function () {
    $container = Docker::nginx()->start();

    try {
        // Wait for nginx to be ready
        sleep(2);

        // Verify nginx is running
        expect(DockerContainerInstance::isRunning($container->getName()))->toBeTrue();

        // Verify can connect to nginx
        $ch = curl_init('http://localhost:80');
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        expect($httpCode)->toBe(200)
            ->and($response)->toContain('nginx');
    } finally {
        $container->stop();
    }
})->group('integration');
