<?php

namespace Ninja\Docker\Exceptions;

use Exception;
use Ninja\Docker\DockerContainer;
use Symfony\Component\Process\Process;

final class CouldNotStartDockerContainer extends Exception
{
    public static function processFailed(DockerContainer $container, Process $process): self
    {
        return new self(
            message:  sprintf(
                "Could not start docker container for image %s`. Process output: `%s`",
                $container->image,
                $process->getErrorOutput()
            )
        );
    }
}
