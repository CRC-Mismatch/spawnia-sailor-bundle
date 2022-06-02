<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Tests\Command;

use Mismatch\SpawniaSailorBundle\Command\SailorEndpointCommand;
use Mismatch\SpawniaSailorBundle\Service\SailorPsr18Client;
use Spawnia\Sailor\EndpointConfig;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Path;
use function is_array;
use function is_string;
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
                $input->bind(new InputDefinition([
                    new InputArgument('endpoint', InputArgument::OPTIONAL),
                    new InputOption('config', 'c', InputOption::VALUE_OPTIONAL),
                ]));
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

    public function testExecuteSingleNoArg(): void
    {
        $endpoints = [
            'test_endpoint' => [
                'url' => 'test',
                'post' => false,
                'namespace' => 'Test\\App',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => '%kernel.project_dir%/var/test/ops',
                'schema_path' => '%kernel.project_dir%/var/test/schema/schema.graphql',
            ],
        ];
        $this->setupKernel($endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        $this->assertExecution($tester, $endpoints);
    }

    public function testExecuteSingleWithArg(): void
    {
        $endpointName = 'test_endpoint';
        $endpoints = [
            $endpointName => [
                'url' => 'test',
                'post' => false,
                'namespace' => 'Test\\App',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => '%kernel.project_dir%/var/test/ops',
                'schema_path' => '%kernel.project_dir%/var/test/schema/schema.graphql',
            ],
        ];
        $this->setupKernel($endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute(['endpoint' => $endpointName]);
        $this->assertExecution($tester, $endpoints, $endpointName);
    }

    public function testExecuteMultipleNoArg(): void
    {
        $endpointName = 'test_endpoint';
        $endpoints = [
            $endpointName => [
                'url' => 'test',
                'post' => false,
                'namespace' => 'Test\\App',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => '%kernel.project_dir%/var/test/ops',
                'schema_path' => '%kernel.project_dir%/var/test/schema/schema.graphql',
            ],
            'whatever' => [
                'url' => 'test2',
                'post' => true,
                'namespace' => 'Test\\App2',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => '%kernel.project_dir%/var/test/ops',
                'schema_path' => '%kernel.project_dir%/var/test/schema/schema.graphql',
            ],
            'else' => [
                'url' => 'test3',
                'post' => false,
                'namespace' => 'Test\\App3',
                'generation_path' => 'var/test/gen',
                'operations_path' => 'var/test/ops',
                'schema_path' => 'var/test/schema/schema.graphql',
            ],
        ];
        $this->setupKernel($endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        $this->assertExecution($tester, $endpoints);
    }

    public function testExecuteMultipleWithArg(): void
    {
        $endpointName = 'test_endpoint';
        $endpoints = [
            $endpointName => [
                'url' => 'test',
                'post' => false,
                'namespace' => 'Test\\App',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => '%kernel.project_dir%/var/test/ops',
                'schema_path' => '%kernel.project_dir%/var/test/schema/schema.graphql',
            ],
            'whatever' => [
                'url' => 'test2',
                'post' => true,
                'namespace' => 'Test\\App2',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => '%kernel.project_dir%/var/test/ops',
                'schema_path' => '%kernel.project_dir%/var/test/schema/schema.graphql',
            ],
            'else' => [
                'url' => 'test3',
                'post' => false,
                'namespace' => 'Test\\App3',
                'generation_path' => 'var/test/gen',
                'operations_path' => 'var/test/ops',
                'schema_path' => 'var/test/schema/schema.graphql',
            ],
        ];
        $chosenEndpoint = [
            $endpointName => $endpoints[$endpointName],
        ];
        $this->setupKernel($endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute(['endpoint' => $endpointName]);
        $this->assertExecution($tester, $chosenEndpoint, $endpointName);
    }

    protected function assertExecution(CommandTester $tester, array $endpoints, ?string $endpoint = null): void
    {
        $endpoints = array_map(static function ($v) {
            foreach ($v as $k => $o) {
                if (!is_string($o)) {
                    continue;
                }
                $v[$k] = str_replace('%kernel.project_dir%', self::getContainer()->getParameter('kernel.project_dir'), $o);
            }

            return $v;
        }, $endpoints);
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $outputData = json_decode($output, true);
        $this->assertArrayHasKey('config', $outputData);
        $this->assertIsString($outputData['config']);
        $config = $outputData['config'];
        unset($outputData['config']);
        $this->assertSame(
            [
                'endpoints' => $endpoints,
                'endpoint' => $endpoint,
            ],
            $outputData
        );
        $configData = require Path::makeRelative($config, __DIR__);
        $this->testGeneratedConfig($configData, $endpoints);
    }

    private function testGeneratedConfig($configData, $endpoints): void
    {
        if (
            !is_array($configData)
            || empty($configData)
            || !array_reduce(
                $configData,
                static fn ($r, $o) => $r && $o instanceof EndpointConfig,
                true
            )
        ) {
            self::fail('Config file invalid');
        }
        foreach ($configData as $name => $endpoint) {
            /* @var $endpoint EndpointConfig */
            $this->assertInstanceOf(SailorPsr18Client::class, $endpoint->makeClient());
            $this->assertSame($endpoints[$name]['namespace'], $endpoint->namespace());
            $this->assertSame($endpoints[$name]['generation_path'], $endpoint->targetPath());
            $this->assertSame($endpoints[$name]['operations_path'], $endpoint->searchPath());
            $this->assertSame($endpoints[$name]['schema_path'], $endpoint->schemaPath());
        }
    }
}
