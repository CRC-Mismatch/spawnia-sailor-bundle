<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Tests\Functional\Command;

use Mismatch\SpawniaSailorBundle\Command\SailorEndpointCommand;
use Mismatch\SpawniaSailorBundle\Service\SailorClientInterface;
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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use function array_map;
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

    private function setupKernel(?string $configPath = null, ?array $endpoints = null): void
    {
        $kernel = self::bootKernel([
            'environment' => 'test',
            'debug' => [
                'suffix' => (string) microtime(),
                'endpoints' => $endpoints,
                'config_path' => $configPath,
            ],
        ]);
        $application = new Application($kernel);
        $this->sailorEndpointCommand = new class(self::getContainer()->getParameterBag(), self::getContainer(), $application) extends SailorEndpointCommand {
            public function __construct(ParameterBag $parameters, ContainerInterface $container, Application $application)
            {
                parent::__construct($parameters, $container);
                $this->setApplication($application);
            }

            protected function getCommandName(): string
            {
                return 'testCommand';
            }

            protected function postExecute(string $configPath, array $endpoints, InputInterface $input, OutputInterface $output): int
            {
                $input->bind(new InputDefinition([
                    new InputArgument('endpoint', InputArgument::OPTIONAL),
                    new InputOption('config', 'c', InputOption::VALUE_OPTIONAL),
                ]));
                $output->write(json_encode([
                    'endpoints' => $endpoints,
                    'endpoint' => $input->getArgument('endpoint'),
                ], JSON_THROW_ON_ERROR));

                return Command::SUCCESS;
            }
        };
        $filesystem = new Filesystem();
        if (!empty($configPath)) {
            $configPath = Path::makeAbsolute($configPath, self::getContainer()->getParameter('kernel.project_dir'));
            $filesystem->remove($configPath);
            if (null !== $endpoints) {
                foreach ($endpoints as $endpoint) {
                    if (empty($endpoint)) {
                        continue;
                    }
                    $filesystem->remove([
                        Path::makeAbsolute($endpoint['generation_path'], $configPath),
                        Path::makeAbsolute($endpoint['operations_path'], $configPath),
                        Path::makeAbsolute($endpoint['schema_path'], $configPath),
                        Path::makeAbsolute("{$endpoint['schema_path']}/..", $configPath),
                    ]);
                }
            }
        }
    }

    public function testFailWithoutConfigPath(): void
    {
        $this->setupKernel('');
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailWithoutEndpoints(): void
    {
        $this->setupKernel();
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailMissingEndpoint(): void
    {
        $configPath = 'var/test/sailor.php';
        $endpoints = [
            'test_endpoint' => [
                'url' => 'test',
                'post' => false,
                'namespace' => 'Test\\App',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => 'var/test/ops',
                'schema_path' => 'schema/schema.graphql',
            ],
        ];
        $this->setupKernel($configPath, $endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute(['endpoint' => 'non-existent']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testExecuteSingleNoArg(): void
    {
        $configPath = 'var/test/sailor.php';
        $endpoints = [
            'test_endpoint' => [
                'url' => 'test',
                'post' => false,
                'namespace' => 'Test\\App',
                'generation_path' => '%kernel.project_dir%/var/test/gen',
                'operations_path' => 'var/test/ops',
                'schema_path' => 'schema/schema.graphql',
            ],
        ];
        $this->setupKernel($configPath, $endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        $this->assertExecution($tester, $endpoints);
    }

    public function testExecuteSingleWithArg(): void
    {
        $configPath = 'var/test/sailor.php';
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
        $this->setupKernel($configPath, $endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute(['endpoint' => $endpointName]);
        $this->assertExecution($tester, $endpoints, $endpointName);
    }

    public function testExecuteMultipleNoArg(): void
    {
        $configPath = 'var/test/sailor.php';
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
        $this->setupKernel($configPath, $endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute([]);
        $this->assertExecution($tester, $endpoints);
    }

    public function testExecuteMultipleWithArg(): void
    {
        $configPath = 'var/test/sailor.php';
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
        $this->setupKernel($configPath, $endpoints);
        $tester = new CommandTester($this->sailorEndpointCommand);
        $tester->execute(['endpoint' => $endpointName]);
        $this->assertExecution($tester, $chosenEndpoint, $endpointName);
    }

    protected function assertExecution(CommandTester $tester, ?array $endpoints = null, ?string $endpoint = null): void
    {
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $outputData = json_decode($output, true); /*
        $this->assertArrayHasKey('config', $outputData);
        $this->assertIsString($outputData['config']);
        $config = $outputData['config'];
        unset($outputData['config']);
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        foreach ($endpoints as &$ep) {
            $ep = array_map(static function ($v) use ($projectDir) {
                if (is_string($v) && str_contains($v, '/')) {
                    $v = str_replace('%kernel.project_dir%/', '', $v);
                    $v = Path::makeAbsolute($v, $projectDir);
                }

                return $v;
            }, $ep);
        }
        unset($ep);
        $this->assertSame(
            [
                'endpoints' => $endpoints,
                'endpoint' => $endpoint,
            ],
            $outputData
        );/*
        $filesystem = new Filesystem();
        $this->assertTrue($filesystem->exists($config));
        $configData = require $config;
        /** @var EndpointConfig $configDatum */
        /*foreach ($configData as $configDatum) {
            $this->assertTrue($filesystem->exists([
                $configDatum->targetPath(),
                $configDatum->searchPath(),
                Path::canonicalize($configDatum->schemaPath().'/..'),
            ]));
            $this->assertInstanceOf(SailorClientInterface::class, $configDatum->makeClient());
        }*/
    }
}
