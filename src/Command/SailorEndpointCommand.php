<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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
        $config = $this->parameters->get('sailor.config');
        $configPath = $this->parameters->get('sailor.config_path');
        if (empty($configPath)) {
            $io->error('The application requires a Sailor config_path to be set before using Sailor commands.');
        }
        if (empty($config) || empty($endpoints)) {
            $io->error('The application must have at least one endpoint configured in order to use Sailor.');
            $io->comment("It's possible that you just need to run cache:clear and try again");

            return Command::FAILURE;
        }
        if ((($endpoint = $input->getArgument('endpoint')) !== null) && !array_key_exists($endpoint, $endpoints)) {
            $io->error("The endpoint '$endpoint' is not known to the current configuration");

            return Command::FAILURE;
        }

        $endpoints = !empty($endpoint) ? [$endpoint => $endpoints[$endpoint]] : $endpoints;

        $this->generateConfigFile(
            $configPath,
            $config,
            $endpoints,
        );

        $argsOpts = [
            '--config' => $configPath,
        ];
        !empty($endpoint) && $argsOpts['endpoint'] = $endpoint;
        $arrayInput = new ArrayInput($argsOpts);

        return $this->postExecute($configPath, $config, $endpoints, $arrayInput, $output);
    }

    protected function generateConfigFile(string $configPath, string $config, array $endpoints): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir(Path::canonicalize("$configPath/.."), 0775);
        foreach ($endpoints as $endpoint) {
            $filesystem->mkdir([
                $endpoint['operations_path'],
                $endpoint['generation_path'],
                Path::canonicalize("{$endpoint['schema_path']}/.."),
            ], 0775);
        }
        $generateFile = true;
        if ($filesystem->exists($configPath)) {
            $currConfig = file_get_contents($configPath);
            $currCode = preg_replace('/.+?return (\[.+])/s', '$1', $currConfig);
            $currCksum = preg_replace('/.+?Original Hash: (.+?)$.+/sm', '$1', $currConfig);
            $actualCksum = hash('fnv1a64', $currCode);
            if (!empty($currCksum) && $actualCksum !== $currCksum) {
                $generateFile = false;
            }
        }
        if ($generateFile) {
            $filesystem->dumpFile($configPath, $config);
        }
    }

    abstract protected function getCommandName(): string;

    protected function postConfigure(): void
    {
    }

    abstract protected function postExecute(string $configPath, string $config, array $endpoints, InputInterface $input, OutputInterface $output): int;
}
