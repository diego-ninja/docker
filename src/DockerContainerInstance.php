<?php

namespace Ninja\Docker;

use JsonException;
use RuntimeException;
use Spatie\Macroable\Macroable;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DockerContainerInstance
{
    use Macroable;

    public const DEFAULT_PATH_AUTHORIZED_KEYS = '/root/.ssh/authorized_keys';

    public function __construct(
        private readonly DockerContainer $config,
        private readonly string $dockerIdentifier,
        private readonly string $name
    ) {}

    public static function fromExisting(string $name): self
    {
        return new self(
            config: new DockerContainer(
                image: self::getImageFromExistingContainer($name),
                name: $name
            ),
            dockerIdentifier: self::getIdFromExistingContainer($name),
            name: $name
        );
    }

    public static function isRunning(string $name): bool
    {
        $process = Process::fromShellCommandline(
            sprintf('docker ps -q -f name=%s', $name)
        );

        $process->run();

        return ! empty(trim($process->getOutput()));
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
        $process     = Process::fromShellCommandline($fullCommand);

        $async ? $process->start() : $process->run();

        return $process;
    }

    public function stop(bool $async = false): Process
    {
        $fullCommand = $this->config->getStopCommand($this->getShortDockerIdentifier());
        $process     = Process::fromShellCommandline($fullCommand);

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

    /**
     * @param string[]|string $command
     * @param bool $async
     * @param float|null $timeout
     * @return Process
     */
    public function execute(array|string $command, bool $async = false, ?float $timeout = 60): Process
    {
        if (is_array($command)) {
            $command = implode(';', $command);
        }

        $fullCommand = $this->config->getExecCommand($this->getShortDockerIdentifier(), $command);

        $process = Process::fromShellCommandline($fullCommand);
        $process->setTimeout($timeout);

        $async ? $process->start() : $process->run();

        return $process;
    }

    public function addPublicKey(
        string $pathToPublicKey,
        string $pathToAuthorizedKeys = self::DEFAULT_PATH_AUTHORIZED_KEYS
    ): self {
        $contents = file_get_contents($pathToPublicKey);
        if ($contents === false) {
            throw new RuntimeException(
                sprintf("Could not read contents of public key at `%s`", $pathToPublicKey)
            );
        }

        $publicKeyContents = trim($contents);

        $this->execute('echo \'' . $publicKeyContents . '\' >> ' . $pathToAuthorizedKeys);

        $this->execute("chmod 600 {$pathToAuthorizedKeys}");
        $this->execute("chown root:root {$pathToAuthorizedKeys}");

        return $this;
    }

    public function addFiles(string $fileOrDirectoryOnHost, string $pathInContainer): self
    {
        $fullCommand = $this->config->getCopyCommand($this->getShortDockerIdentifier(), $fileOrDirectoryOnHost, $pathInContainer);

        $process = Process::fromShellCommandline($fullCommand);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    public function inspect(): array
    {
        $fullCommand = $this->config->getInspectCommand($this->getShortDockerIdentifier());

        $process = Process::fromShellCommandline($fullCommand);
        $process->run();

        $json = trim($process->getOutput());

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function getImageFromExistingContainer(string $name): string
    {
        $process = Process::fromShellCommandline(
            sprintf('docker ps --format="{{.Image}}" -f name=%s', $name)
        );

        $process->run();

        return trim($process->getOutput());
    }

    private static function getIdFromExistingContainer(string $name): string
    {
        $process = Process::fromShellCommandline(
            sprintf('docker ps -q -f name=%s', $name)
        );

        $process->run();

        return trim($process->getOutput());
    }

}
