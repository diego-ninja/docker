<?php

// ABOUTME: Integration tests for safe command execution with special characters.
// ABOUTME: Verifies execute() method handles shell metacharacters and input safely.

declare(strict_types=1);

use Ninja\Docker\DockerContainer;

it('executes commands safely with special characters', function () {
    $container = DockerContainer::create('nginx:alpine')
        ->name('exec-test-container-' . uniqid())
        ->shell('sh')
        ->start();

    $output = $container->execute('echo "test with spaces and \'special\' chars"');

    expect($output)->toContain("test with spaces and 'special' chars");
})->group('integration');
