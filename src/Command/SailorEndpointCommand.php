<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

use function array_key_exists;

abstract class SailorEndpointCommand extends Command
{
    protected ParameterBagInterface $parameters;
    protected ContainerInterface $container;
    protected array $endpoints = [];

    public function __construct(ParameterBagInterface $parameters, ContainerInterface $container)
    {
        parent::__construct();
        $this->parameters = $parameters;
        $this->container = $container;
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
        $configPath = $this->parameters->get('sailor.config_path');
        if (empty($configPath)) {
            $io->error('The application requires a Sailor config_path to be set before using Sailor commands.');
        }
        if (empty($endpoints)) {
            $io->error('The application must have at least one endpoint configured in order to use Sailor.');
            $io->comment("It's possible that you just need to run cache:clear and try again");

            return Command::FAILURE;
        }
        if ((($endpoint = $input->getArgument('endpoint')) !== null) && !array_key_exists($endpoint, $endpoints)) {
            $io->error("The endpoint '$endpoint' is not known to the current configuration");

            return Command::FAILURE;
        }

        $endpoints = !empty($endpoint) ? [$endpoint => $endpoints[$endpoint]] : $endpoints;

        foreach ($endpoints as $name => $value) {
            $this->container->get("sailor.$name.endpoint_config");
        }

        $this->touchConfig($configPath);

        $argsOpts = [
            '--config' => $configPath,
        ];
        !empty($endpoint) && $argsOpts['endpoint'] = $endpoint;
        $arrayInput = new ArrayInput($argsOpts);

        return $this->postExecute($configPath, $endpoints, $arrayInput, $output);
    }

    protected function touchConfig(string $configPath): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir(Path::canonicalize("$configPath/.."), 0775);
        if (!$filesystem->exists($configPath)) {
            $filesystem->touch($configPath);
        }
    }

    abstract protected function getCommandName(): string;

    protected function postConfigure(): void
    {
    }

    abstract protected function postExecute(string $configPath, array $endpoints, InputInterface $input, OutputInterface $output): int;
}
