<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Tests\Command;

use Mismatch\SpawniaSailorBundle\Command\SailorEndpointCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use const JSON_THROW_ON_ERROR;

class SailorEndpointCommandTest extends KernelTestCase
{
    private SailorEndpointCommand $sailorEndpointCommand;

    protected static function getContainer(): ContainerInterface
    {
        if (method_exists(KernelTestCase::class, 'getContainer')) {
            return parent::getContainer();
        }
        return self::$kernel->getContainer();
    }

    private function setupKernel(array $endpoints = []): void
    {
        $kernel = self::bootKernel([
            'environment' => 'test',
            'debug' => [
                'suffix' => (string) microtime(),
                'endpoints' => $endpoints,
            ],
        ]);
        $application = new Application($kernel);
        $this->sailorEndpointCommand = new class(self::getContainer()->getParameterBag(), $application) extends SailorEndpointCommand {
            public function __construct(ParameterBag $parameters, Application $application)
            {
                parent::__construct($parameters);
                $this->setApplication($application);
            }

            protected function getCommandName(): string
            {
                return 'testCommand';
            }

            protected function postExecute(InputInterface $input, OutputInterface $output): int
            {
                $output->write(json_encode([
                    'endpoints' => $this->endpoints,
                    'endpoint' => $input->getArgument('endpoint'),
                    'config' => $input->getOption('config'),
                ], JSON_THROW_ON_ERROR));

                return Command::SUCCESS;
            }
        };
    }

    public function testFailWithoutEndpoints(): void
    {
        $this->setupKernel([]);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testExecute(): void
    {
        $this->setupKernel([
            'test_endpoint' => [
                'url' => 'test',
                'post' => false,
                'namespace' => 'Test\\App',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => '%kernel.project_dir%/var/test/ops',
                'schema_path' => '%kernel.project_dir%/var/test/schema/schema.graphql',
            ],
        ]);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
    }
}
