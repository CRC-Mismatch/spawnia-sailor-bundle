<?php

/**
 * @copyright  Copyright (c) 2023 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 *
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Command;

use Spawnia\Sailor\Console\CodegenCommand;
use Spawnia\Sailor\Console\IntrospectCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SailorUpdateCommand extends SailorEndpointCommand
{
    protected function getCommandName(): string
    {
        return 'sailor:update';
    }

    protected function postConfigure(): void
    {
        $this->setDescription('Update remote introspection schema and regenerate code from local GraphQL files.');
    }

    /**
     * @throws ExceptionInterface
     */
    protected function postExecute(string $configPath, array $endpoints, InputInterface $input, OutputInterface $output): int
    {
        $result = (new IntrospectCommand())->run($input, $output);
        if ($result) {
            return $result;
        }

        return (new CodegenCommand())->run($input, $output);
    }
}
