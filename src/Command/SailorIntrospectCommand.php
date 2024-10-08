<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Command;

use Exception;
use Spawnia\Sailor\Console\IntrospectCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SailorIntrospectCommand extends SailorEndpointCommand
{
    protected function getCommandName(): string
    {
        return 'sailor:introspect';
    }

    protected function postConfigure(): void
    {
        $this->setDescription('Download a remote schema through introspection.');
    }

    /**
     * @throws ExceptionInterface
     */
    protected function postExecute(string $configPath, array $endpoints, InputInterface $input, OutputInterface $output): int
    {
        return (new IntrospectCommand())->run($input, $output);
    }
}
