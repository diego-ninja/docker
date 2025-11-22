<?php

// ABOUTME: Configures and manages Docker container lifecycle and execution settings.
// ABOUTME: Provides fluent API for container configuration before starting instances.

declare(strict_types=1);

namespace Ninja\Docker;

use Ninja\Docker\Exceptions\CouldNotStartDockerContainer;
use Ninja\Docker\Mappings\BindMountMapping;
use Ninja\Docker\Mappings\EnvironmentMapping;
use Ninja\Docker\Mappings\LabelMapping;
use Ninja\Docker\Mappings\NamedVolumeMapping;
use Ninja\Docker\Mappings\PortMapping;
use Ninja\Docker\Mappings\VolumeMapping;
use Ninja\Docker\ValueObjects\ContainerName;
use Ninja\Docker\ValueObjects\ContainerPath;
use Ninja\Docker\ValueObjects\HostPath;
use Ninja\Docker\ValueObjects\ImageName;
use Ninja\Docker\ValueObjects\NetworkName;
use Ninja\Docker\ValueObjects\RemoteHost;
use Ninja\Docker\ValueObjects\VolumeName;
use Spatie\Macroable\Macroable;
use Symfony\Component\Process\Process;

class DockerContainer
{
    use Macroable;

    public bool $daemonize = true;

    public bool $privileged = false;

    public string $shell = 'bash';

    public ?NetworkName $network = null;

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

    /** @var list<BindMountMapping> */
    private array $_bindMounts = [];

    /**
     * @var list<BindMountMapping>
     */
    public array $bindMounts {
        /** @return list<BindMountMapping> */
        get => $this->_bindMounts;
    }

    /** @var list<NamedVolumeMapping> */
    private array $_namedVolumes = [];

    /**
     * @var list<NamedVolumeMapping>
     */
    public array $namedVolumes {
        /** @return list<NamedVolumeMapping> */
        get => $this->_namedVolumes;
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

    public ?RemoteHost $remoteHost = null;

    public string $command = '';

    /** @var list<string> */
    public array $optionalArgs = [];

    /** @var list<string> */
    public array $commands = [];

    protected float $startCommandTimeout = 60;

    final public function __construct(
        public ImageName $image,
        public ?ContainerName $name = null
    ) {}

    public static function create(ImageName|string $image, ContainerName|string|null $name = null): self
    {
        $imageVO = $image instanceof ImageName ? $image : ImageName::from($image);
        $nameVO  = $name === null ? null : ($name instanceof ContainerName ? $name : ContainerName::from($name));

        return new static($imageVO, $nameVO);
    }

    public function image(ImageName|string $image): self
    {
        $this->image = $image instanceof ImageName ? $image : ImageName::from($image);

        return $this;
    }

    public function name(ContainerName|string $name): self
    {
        $this->name = $name instanceof ContainerName ? $name : ContainerName::from($name);

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

    public function network(NetworkName|string $network): self
    {
        $this->network = $network instanceof NetworkName ? $network : NetworkName::from($network);

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

    /**
     * @deprecated Use bindMount() or namedVolume() instead for explicit volume type handling
     */
    public function setVolume(string $pathOnHost, string $pathOnDocker): self
    {
        $this->_volumeMappings[] = new VolumeMapping($pathOnHost, $pathOnDocker);

        return $this;
    }

    public function bindMount(
        HostPath|string $source,
        ContainerPath|string $target,
        string $flags = ''
    ): self {
        $this->_bindMounts[] = new BindMountMapping($source, $target, $flags);

        return $this;
    }

    public function namedVolume(
        VolumeName|string $name,
        ContainerPath|string $target,
        string $flags = ''
    ): self {
        $this->_namedVolumes[] = new NamedVolumeMapping($name, $target, $flags);

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

    public function remoteHost(RemoteHost|string $remoteHost): self
    {
        $this->remoteHost = $remoteHost instanceof RemoteHost ? $remoteHost : RemoteHost::from($remoteHost);

        return $this;
    }

    public function command(string $command): self
    {
        $this->command = $command;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getBaseCommand(): array
    {
        return [
            'docker',
            ...$this->getExtraDockerOptions(),
        ];
    }

    /**
     * @return list<string>
     */
    public function getRunCommand(): array
    {
        $runCommand = [
            ...$this->getBaseCommand(),
            'run',
            ...$this->getExtraOptions(),
            (string)$this->image,
            ...$this->commands,
        ];

        if ($this->command !== '') {
            $runCommand[] = $this->command;
        }

        return $runCommand;
    }

    /**
     * @return list<string>
     */
    public function getStopCommand(string $dockerIdentifier): array
    {
        return [
            ...$this->getBaseCommand(),
            'stop',
            $dockerIdentifier,
        ];
    }

    /**
     * @return list<string>
     */
    public function getStartCommand(string $dockerIdentifier): array
    {
        return [
            ...$this->getBaseCommand(),
            'start',
            $dockerIdentifier,
        ];
    }

    /**
     * @return list<string>
     */
    public function getExecCommand(string $dockerIdentifier, string $command): array
    {
        return [
            ...$this->getBaseCommand(),
            'exec',
            '--interactive',
            $dockerIdentifier,
            $this->shell,
            '-c',
            $command,
        ];
    }

    /**
     * @return list<string>
     */
    public function getCopyCommand(string $dockerIdentifier, string $fileOrDirectoryOnHost, string $pathInContainer): array
    {
        return [
            ...$this->getBaseCommand(),
            'cp',
            $fileOrDirectoryOnHost,
            "{$dockerIdentifier}:{$pathInContainer}",
        ];
    }

    /**
     * @return list<string>
     */
    public function getInspectCommand(string $dockerIdentifier): array
    {
        return [
            ...$this->getBaseCommand(),
            'inspect',
            $dockerIdentifier,
        ];
    }

    /**
     * @param callable(Process): void|null $callback
     * @return DockerContainerInstance
     * @throws CouldNotStartDockerContainer
     */
    public function start(?callable $callback = null): DockerContainerInstance
    {
        $command = $this->getRunCommand();

        $process = new Process($command);
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
            $this->name?->value ?: $dockerIdentifier,
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
            array_push($extraOptions, ...$this->optionalArgs);
        }

        foreach ($this->_portMappings as $mapping) {
            $extraOptions[] = '-p';
            $extraOptions[] = (string)$mapping;
        }

        foreach ($this->_environmentMappings as $mapping) {
            $extraOptions[] = '-e';
            $extraOptions[] = (string)$mapping;
        }

        foreach ($this->_volumeMappings as $mapping) {
            $extraOptions[] = '-v';
            $extraOptions[] = (string)$mapping;
        }

        foreach ($this->_bindMounts as $mount) {
            $extraOptions[] = '-v';
            $extraOptions[] = (string)$mount;
        }

        foreach ($this->_namedVolumes as $volume) {
            $extraOptions[] = '-v';
            $extraOptions[] = (string)$volume;
        }

        foreach ($this->_labelMappings as $label) {
            $extraOptions[] = '-l';
            $extraOptions[] = (string)$label;
        }

        if ($this->name !== null) {
            $extraOptions[] = '--name';
            $extraOptions[] = (string)$this->name;
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

        if ($this->network !== null) {
            $extraOptions[] = '--network';
            $extraOptions[] = (string)$this->network;
        }

        return $extraOptions;
    }

    /**
     * @return list<string>
     */
    protected function getExtraDockerOptions(): array
    {
        $extraDockerOptions = [];

        if ($this->remoteHost !== null) {
            $extraDockerOptions[] = '-H';
            $extraDockerOptions[] = (string)$this->remoteHost;
        }

        return $extraDockerOptions;
    }
}
