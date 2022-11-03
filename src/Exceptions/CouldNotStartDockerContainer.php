<?php

namespace Ninja\Docker\Exceptions;

use Exception;
use Ninja\Docker\DockerContainer;
use Symfony\Component\Process\Process;

class CouldNotStartDockerContainer extends Exception
{
    public static function processFailed(DockerContainer $container, Process $process): static
    {
        return new static(
            message:  sprintf(
                "Could not start docker container for image %s`. Process output: `%s`",
                $container->image,
                $process->getErrorOutput()
            )
        );
    }
}
