<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Command;

use Exception;
use Spawnia\Sailor\Console\CodegenCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SailorGenerateCommand extends SailorEndpointCommand
{
    protected function getCommandName(): string
    {
        return 'sailor:codegen';
    }

    protected function postConfigure(): void
    {
        $this->setDescription('Generate code from your GraphQL files.');
    }

    /**
     * @throws Exception
     */
    protected function postExecute(string $configPath, array $endpoints, InputInterface $input, OutputInterface $output): int
    {
        return (new CodegenCommand())->run($input, $output);
    }
}
