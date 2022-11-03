<?php

declare(strict_types=1);

use Ninja\Docker\DockerContainerInstance;

require "vendor/autoload.php";

$container = DockerContainerInstance::fromExisting("c07a3c6aee03", "php");
//$container->stop();
//$container->start();
print_r($container->inspect());
