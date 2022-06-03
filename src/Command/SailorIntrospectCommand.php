<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Command;

use Exception;
use Spawnia\Sailor\Console\IntrospectCommand;
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
     * @throws Exception
     */
    protected function postExecute(string $configPath, string $config, array $endpoints, InputInterface $input, OutputInterface $output): int
    {
        return (new IntrospectCommand())->run($input, $output);
    }
}
