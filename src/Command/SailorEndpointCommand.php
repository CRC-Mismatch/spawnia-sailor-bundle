<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Command;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Spawnia\Sailor\Client;
use Spawnia\Sailor\EndpointConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use function array_key_exists;

abstract class SailorEndpointCommand extends Command
{
    protected ParameterBagInterface $parameters;
    protected array $endpoints = [];

    public function __construct(ParameterBagInterface $parameters)
    {
        parent::__construct();
        $this->parameters = $parameters;
    }

    /**
     * {@inheritDoc}
     */
    final protected function configure(): void
    {
        $this->setName($this->getCommandName());
        $this->addArgument(
            'endpoint',
            InputArgument::OPTIONAL,
            'You may choose a specific endpoint. Uses all by default.'
        );
        $this->postConfigure();
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $endpoints = $this->parameters->get('sailor.endpoints');
        if (empty($endpoints)) {
            $io->error('The application must have at least one endpoint configured in order to use Sailor');

            return Command::FAILURE;
        }
        $endpoints = array_column($endpoints, null, 'name');
        if (($endpoint = $input->getArgument('endpoint')) !== null) {
            if (!array_key_exists($endpoint, $endpoints)) {
                $io->error("The endpoint '$endpoint' is not known to the current configuration");

                return Command::FAILURE;
            }
            $this->endpoints[$endpoint] = $endpoints[$endpoint];
        } else {
            $this->endpoints = $endpoints;
        }

        $this->addOption('config');
        $input->setOption('config', $this->treatEndpoints());

        return $this->postExecute($input, $output);
    }

    private function treatEndpoints(): string
    {
        $filesystem = new Filesystem();
        $tempConfig = $filesystem->tempnam('/tmp', 'slr', '.php');
        foreach ($this->endpoints as $name => $endpoint) {
            $filesystem->mkdir([
                Path::makeAbsolute($endpoint['operations_path'], $this->parameters->get('kernel.project_dir')),
                Path::makeAbsolute($endpoint['generation_path'], $this->parameters->get('kernel.project_dir')),
                Path::makeAbsolute(Path::canonicalize($endpoint['schema_path'].'/..'), $this->parameters->get('kernel.project_dir')),
            ], 0775);
        }
        $config = $this->generateConfigFile($tempConfig);
        $filesystem->dumpFile($tempConfig, $config);

        return $tempConfig;
    }

    private function generateConfigFile(string $path): string
    {
        $endpoints = [];
        foreach ($this->endpoints as $name => $options) {
            $configClass = (new ClassType())
                ->setExtends(EndpointConfig::class);
            $configClass->addMethod('makeClient')
                ->setPublic()
                ->setReturnType(Client::class)
                ->addBody('return (new \\Mismatch\\SpawniaSailorBundle\\Service\\SailorPsr18Client())')
                ->addBody('->setUrl(?)', [$options['url']])
                ->addBody('->setPost(?);', [$options['post']]);
            $configClass->addMethod('namespace')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['namespace']]);
            $configClass->addMethod('targetPath')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['generation_path']]);
            $configClass->addMethod('searchPath')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['operations_path']]);
            $configClass->addMethod('schemaPath')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['schema_path']]);
            $endpoints[$name] = (string) $configClass;
        }
        $configArrStr = "[\n";
        foreach ($endpoints as $name => $code) {
            $configArrStr .= "  '$name' => new class() $code,\n";
        }
        $configArrStr .= '];';
        $config = new PhpFile();
        $config
            ->setStrictTypes()
            ->addComment('This file is auto-generated.')
            ->addUse(EndpointConfig::class);

        return "$config\nreturn $configArrStr";
    }

    abstract protected function getCommandName(): string;

    protected function postConfigure(): void
    {
    }

    abstract protected function postExecute(InputInterface $input, OutputInterface $output): int;
}
