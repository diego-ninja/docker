<?php

// ABOUTME: Configures and manages Docker container lifecycle and execution settings.
// ABOUTME: Provides fluent API for container configuration before starting instances.

declare(strict_types=1);

namespace Ninja\Docker;

use Ninja\Docker\Exceptions\CouldNotStartDockerContainer;
use Spatie\Macroable\Macroable;
use Symfony\Component\Process\Process;

class DockerContainer
{
    use Macroable;

    public bool $daemonize = true;

    public bool $privileged = false;

    public string $shell = 'bash';

    public ?string $network = null;

    /** @var list<PortMapping|\Stringable> */
    private array $_portMappings = [];

    /**
     * @var list<PortMapping|\Stringable>
     */
    public array $portMappings {
        /** @return list<PortMapping|\Stringable> */
        get => $this->_portMappings;
    }

    /** @var list<EnvironmentMapping> */
    private array $_environmentMappings = [];

    /**
     * @var list<EnvironmentMapping>
     */
    public array $environmentMappings {
        /** @return list<EnvironmentMapping> */
        get => $this->_environmentMappings;
    }

    /** @var list<VolumeMapping> */
    private array $_volumeMappings = [];

    /**
     * @var list<VolumeMapping>
     */
    public array $volumeMappings {
        /** @return list<VolumeMapping> */
        get => $this->_volumeMappings;
    }

    /** @var list<LabelMapping> */
    private array $_labelMappings = [];

    /**
     * @var list<LabelMapping>
     */
    public array $labelMappings {
        /** @return list<LabelMapping> */
        get => $this->_labelMappings;
    }

    public bool $cleanUpAfterExit = true;

    public bool $stopOnDestruct = false;

    public string $remoteHost = '';

    public string $command = '';

    /** @var list<string> */
    public array $optionalArgs = [];

    /** @var list<string> */
    public array $commands = [];

    protected float $startCommandTimeout = 60;

    final public function __construct(public string $image, public string $name = '') {}

    public static function create(string $image, string $name = ''): self
    {
        return new static($image, $name);
    }

    public function image(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function daemonize(bool $daemonize = true): self
    {
        $this->daemonize = $daemonize;

        return $this;
    }

    public function privileged(bool $privileged = true): self
    {
        $this->privileged = $privileged;

        return $this;
    }

    public function shell(string $shell): self
    {
        $this->shell = $shell;

        return $this;
    }

    public function network(string $network): self
    {
        $this->network = $network;

        return $this;
    }

    public function doNotDaemonize(): self
    {
        $this->daemonize = false;

        return $this;
    }

    public function cleanUpAfterExit(bool $cleanUpAfterExit): self
    {
        $this->cleanUpAfterExit = $cleanUpAfterExit;

        return $this;
    }

    public function doNotCleanUpAfterExit(): self
    {
        $this->cleanUpAfterExit = false;

        return $this;
    }

    public function mapPort(int|string $portOnHost, int $portOnDocker): self
    {
        // For string ports (IP:port format like "127.0.0.1:4848"), we store a simple object
        // For int ports, we use PortMapping which validates the port numbers
        if (is_string($portOnHost)) {
            $this->_portMappings[] = new class ($portOnHost, $portOnDocker) {
                public function __construct(
                    private readonly string $hostSpec,
                    private readonly int $containerPort
                ) {}

                public function __toString(): string
                {
                    return "{$this->hostSpec}:{$this->containerPort}";
                }
            };
        } else {
            $this->_portMappings[] = new PortMapping($portOnHost, $portOnDocker);
        }

        return $this;
    }

    public function setEnvironmentVariable(string $envName, string $envValue): self
    {
        $this->_environmentMappings[] = new EnvironmentMapping(
            \Ninja\Docker\ValueObjects\EnvironmentVariable::from($envName, $envValue)
        );

        return $this;
    }

    public function setVolume(string $pathOnHost, string $pathOnDocker): self
    {
        $this->_volumeMappings[] = new VolumeMapping($pathOnHost, $pathOnDocker);

        return $this;
    }

    public function setLabel(string $labelName, string $labelValue): self
    {
        $this->_labelMappings[] = new LabelMapping($labelName, $labelValue);

        return $this;
    }

    /**
     * @param string ...$args
     * @return self
     */
    public function setOptionalArgs(string ...$args): self
    {
        $this->optionalArgs = array_values($args);

        return $this;
    }

    /**
     * @param string ...$args
     * @return self
     */
    public function setCommands(string ...$args): self
    {
        $this->commands = array_values($args);

        return $this;
    }

    public function stopOnDestruct(bool $stopOnDestruct = true): self
    {
        $this->stopOnDestruct = $stopOnDestruct;

        return $this;
    }

    public function remoteHost(string $remoteHost): self
    {
        $this->remoteHost = $remoteHost;

        return $this;
    }

    public function command(string $command): self
    {
        $this->command = $command;

        return $this;
    }

    public function getBaseCommand(): string
    {
        $baseCommand = [
            'docker',
            ...$this->getExtraDockerOptions(),
        ];

        return implode(' ', $baseCommand);
    }

    public function getRunCommand(): string
    {
        $runCommand = [
            $this->getBaseCommand(),
            'run',
            ...$this->getExtraOptions(),
            $this->image,
            ...$this->commands,
        ];

        if ($this->command !== '') {
            $runCommand[] = $this->command;
        }

        return implode(' ', $runCommand);
    }

    public function getStopCommand(string $dockerIdentifier): string
    {
        $stopCommand = [
            $this->getBaseCommand(),
            'stop',
            $dockerIdentifier,
        ];

        return implode(' ', $stopCommand);
    }

    public function getStartCommand(string $dockerIdentifier): string
    {
        $startCommand = [
            $this->getBaseCommand(),
            'start',
            $dockerIdentifier,
        ];

        return implode(' ', $startCommand);
    }

    public function getExecCommand(string $dockerIdentifier, string $command): string
    {
        $execCommand = [
            "echo \"{$command}\"",
            '|',
            $this->getBaseCommand(),
            'exec',
            '--interactive',
            $dockerIdentifier,
            $this->shell,
            '-',
        ];

        return implode(' ', $execCommand);
    }

    public function getCopyCommand(string $dockerIdentifier, string $fileOrDirectoryOnHost, string $pathInContainer): string
    {
        $copyCommand = [
            $this->getBaseCommand(),
            'cp',
            $fileOrDirectoryOnHost,
            "{$dockerIdentifier}:{$pathInContainer}",
        ];

        return implode(' ', $copyCommand);
    }

    public function getInspectCommand(string $dockerIdentifier): string
    {
        $execCommand = [
            $this->getBaseCommand(),
            'inspect',
            $dockerIdentifier,
        ];

        return implode(' ', $execCommand);
    }

    /**
     * @param callable(Process): void|null $callback
     * @return DockerContainerInstance
     * @throws CouldNotStartDockerContainer
     */
    public function start(?callable $callback = null): DockerContainerInstance
    {
        $command = $this->getRunCommand();

        $process = Process::fromShellCommandline($command);
        $process->setTimeout($this->startCommandTimeout);

        if ($callback) {
            $process->start();
            while ($process->isRunning()) {
                $callback($process);
            }
        } else {
            $process->run();
        }

        if (!$process->isSuccessful()) {
            throw CouldNotStartDockerContainer::processFailed($this, $process);
        }

        $dockerIdentifier = trim($process->getOutput());

        return new DockerContainerInstance(
            $this,
            $dockerIdentifier,
            $this->name,
        );
    }

    public function setStartCommandTimeout(float $timeout): self
    {
        $this->startCommandTimeout = $timeout;

        return $this;
    }

    public function getStartCommandTimeout(): float
    {
        return $this->startCommandTimeout;
    }

    /**
     * @return list<string>
     */
    protected function getExtraOptions(): array
    {
        $extraOptions = [];

        if ($this->optionalArgs) {
            $extraOptions[] = implode(' ', $this->optionalArgs);
        }

        if (count($this->_portMappings)) {
            $mappings       = array_map(fn($mapping) => "-p {$mapping}", $this->_portMappings);
            $extraOptions[] = implode(' ', $mappings);
        }

        if (count($this->_environmentMappings)) {
            $mappings       = array_map(fn($mapping) => "-e {$mapping}", $this->_environmentMappings);
            $extraOptions[] = implode(' ', $mappings);
        }

        if (count($this->_volumeMappings)) {
            $extraOptions[] = implode(' ', $this->_volumeMappings);
        }

        if (count($this->_labelMappings)) {
            $extraOptions[] = implode(' ', $this->_labelMappings);
        }

        if ($this->name !== '') {
            $extraOptions[] = "--name {$this->name}";
        }

        if ($this->daemonize) {
            $extraOptions[] = '-d';
        }

        if ($this->privileged) {
            $extraOptions[] = '--privileged';
        }

        if ($this->cleanUpAfterExit) {
            $extraOptions[] = '--rm';
        }

        if ($this->network) {
            $extraOptions[] = '--network ' . $this->network;
        }

        return $extraOptions;
    }

    /**
     * @return list<string>
     */
    protected function getExtraDockerOptions(): array
    {
        $extraDockerOptions = [];

        if ($this->remoteHost !== '') {
            $extraDockerOptions[] = "-H {$this->remoteHost}";
        }

        return $extraDockerOptions;
    }
}
