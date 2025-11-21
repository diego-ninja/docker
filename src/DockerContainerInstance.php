<?php

// ABOUTME: Represents a running Docker container instance with execution capabilities.
// ABOUTME: Provides methods to execute commands, manage files, and inspect container state.

declare(strict_types=1);

namespace Ninja\Docker;

use JsonException;
use Ninja\Docker\ValueObjects\ContainerPath;
use Ninja\Docker\ValueObjects\HostPath;
use RuntimeException;
use Spatie\Macroable\Macroable;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DockerContainerInstance
{
    use Macroable;

    public const string DEFAULT_PATH_AUTHORIZED_KEYS = '/root/.ssh/authorized_keys';

    public function __construct(
        private readonly DockerContainer $config,
        private readonly string $dockerIdentifier,
        private readonly string $name
    ) {}

    public static function fromExisting(string $name): self
    {
        return new self(
            config: DockerContainer::create(
                image: self::getImageFromExistingContainer($name),
                name: $name
            ),
            dockerIdentifier: self::getIdFromExistingContainer($name),
            name: $name
        );
    }

    public static function isRunning(string $name): bool
    {
        $process = new Process([
            'docker',
            'ps',
            '-q',
            '-f',
            "name={$name}",
        ]);

        $process->run();

        return !empty(trim($process->getOutput()));
    }

    public function __destruct()
    {
        if ($this->config->stopOnDestruct) {
            $this->stop();
        }
    }

    public function start(bool $async = false): Process
    {
        $fullCommand = $this->config->getStartCommand($this->getShortDockerIdentifier());
        $process     = new Process($fullCommand);

        $async ? $process->start() : $process->run();

        return $process;
    }

    public function stop(bool $async = false): Process
    {
        $fullCommand = $this->config->getStopCommand($this->getShortDockerIdentifier());
        $process     = new Process($fullCommand);

        $async ? $process->start() : $process->run();

        return $process;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): DockerContainer
    {
        return $this->config;
    }

    public function getDockerIdentifier(): string
    {
        return $this->dockerIdentifier;
    }

    public function getShortDockerIdentifier(): string
    {
        return substr($this->dockerIdentifier, 0, 12);
    }

    public function execute(string $command): string
    {
        $dockerCommand = array_merge(
            $this->config->getBaseCommand(),
            ['exec', '--interactive', $this->getShortDockerIdentifier(), $this->config->shell]
        );

        $process = new Process($dockerCommand);
        $process->setInput($command);
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "Command execution failed: {$process->getErrorOutput()}"
            );
        }

        return $process->getOutput();
    }

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

        $contents = file_get_contents((string) $publicKeyPath);
        if ($contents === false) {
            throw new RuntimeException(
                sprintf("Could not read contents of public key at %s", (string) $publicKeyPath)
            );
        }

        $publicKeyContents = trim($contents);
        $sshDir = dirname((string) $authorizedKeysPath);

        $this->execute("mkdir -p {$sshDir}");
        $this->execute("chmod 700 {$sshDir}");
        $this->execute("echo '{$publicKeyContents}' >> {$authorizedKeysPath}");
        $this->execute("chmod 600 {$authorizedKeysPath}");
        $this->execute("chown root:root {$authorizedKeysPath}");

        return $this;
    }

    /**
     * @param string $fileOrDirectoryOnHost
     * @param string $pathInContainer
     * @return self
     * @throws ProcessFailedException
     */
    public function addFiles(string $fileOrDirectoryOnHost, string $pathInContainer): self
    {
        $fullCommand = $this->config->getCopyCommand($this->getShortDockerIdentifier(), $fileOrDirectoryOnHost, $pathInContainer);

        $process = new Process($fullCommand);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     * @throws JsonException
     */
    public function inspect(): array
    {
        $fullCommand = $this->config->getInspectCommand($this->getShortDockerIdentifier());

        $process = new Process($fullCommand);
        $process->run();

        $json = trim($process->getOutput());

        /** @var list<array<string, mixed>> $result */
        $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $result;
    }

    private static function getImageFromExistingContainer(string $name): string
    {
        $process = new Process([
            'docker',
            'ps',
            '--format={{.Image}}',
            '-f',
            "name={$name}",
        ]);

        $process->run();

        return trim($process->getOutput());
    }

    private static function getIdFromExistingContainer(string $name): string
    {
        $process = new Process([
            'docker',
            'ps',
            '-q',
            '-f',
            "name={$name}",
        ]);

        $process->run();

        return trim($process->getOutput());
    }

}
