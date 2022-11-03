<?php

namespace Ninja\Docker\Tests;

use PHPUnit\Framework\TestCase;
use Ninja\Docker\DockerContainer;
use Ninja\Docker\DockerContainerInstance;
use Ninja\Docker\Exceptions\CouldNotStartDockerContainer;
use Spatie\Ssh\Ssh;

class FeatureTest extends TestCase
{
    private DockerContainer $container;

    private Ssh $ssh;

    public function setUp(): void
    {
        parent::setUp();

        $this->container = (new DockerContainer('spatie/docker'))
            ->name('spatie_docker_test')
            ->mapPort(4848, 22)
            ->stopOnDestruct();

        $this->ssh = (new Ssh('root', '0.0.0.0', 4848))
            ->usePrivateKey(__DIR__.'/keys/spatie_docker_package_id_rsa');
    }

    /** @test */
    public function it_can_start_a_container()
    {
        $container = $this->container->start();

        $this->assertGreaterThan(0, strlen($container->getDockerIdentifier()));
    }

    /** @test */
    public function a_public_key_can_be_added_to_a_running_container()
    {
        $container = (new DockerContainer('spatie/docker'))
            ->name('spatie_docker_test')
            ->mapPort(4848, 22)
            ->stopOnDestruct()
            ->start()
            ->addPublicKey(__DIR__.'/keys/spatie_docker_package_id_rsa.pub');

        $process = $this->ssh->execute('whoami');

        $this->assertEquals('root', trim($process->getOutput()));

        $container->stop();
    }

    /** @test */
    public function files_can_be_added_to_the_container()
    {
        $container = $this->container->start()
            ->addPublicKey(__DIR__.'/keys/spatie_docker_package_id_rsa.pub')
            ->addFiles(__DIR__.'/stubs', '/test');

        $process = $this->ssh->execute([
            'cd /test',
            'find .',
        ]);

        $filesOnContainer = array_filter(explode(PHP_EOL, $process->getOutput()));

        $this->assertEqualsCanonicalizing([
            '.',
            './subDirectory',
            './subDirectory/1.txt',
            './subDirectory/2.txt',
        ], $filesOnContainer);

        $container->stop();
    }

    /** @test */
    public function it_will_throw_an_exception_if_the_container_could_not_start()
    {
        $this->expectException(CouldNotStartDockerContainer::class);

        (new DockerContainer('non-existing-image'))->start();
    }

    /** @test */
    public function the_docker_container_is_macroable()
    {
        DockerContainerInstance::macro('whoAmI', function () {
            /** @var \Symfony\Component\Process\Process $process */
            $process = $this->execute('whoami');

            return trim($process->getOutput());
        });

        $userName = (new DockerContainer('spatie/docker'))
            ->name('spatie_docker_test')
            ->mapPort(4848, 22)
            ->stopOnDestruct()
            ->start()
            ->whoAmI();

        $this->assertEquals('root', $userName);
    }

    /** @test */
    public function docker_inspect_information_can_be_retrieved()
    {
        $container = (new DockerContainer('spatie/docker'))
            ->name('spatie_docker_test')
            ->stopOnDestruct()
            ->start();

        $info = $container->inspect();
        $this->assertEquals($container->getDockerIdentifier(), $info[0]['Id']);

        $container->stop();
    }
}
