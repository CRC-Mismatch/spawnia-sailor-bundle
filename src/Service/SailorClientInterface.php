<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use Spawnia\Sailor\Client;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\Operation;
use Spawnia\Sailor\Response;
use Spawnia\Sailor\Result;
use stdClass;

interface SailorClientInterface extends Client
{
    /**
     * @template TResult of \Spawnia\Sailor\Result
     *
     * @param Operation<TResult> $operation
     * @param array              $args
     *
     * @throws InvalidDataException whenever the client receives a status other than 200 OK and valid JSON/GraphQL results
     *
     * @return TResult
     */
    public function execute(Operation $operation, ...$args): Result;

    /**
     * {@inheritDoc}
     *
     * @throws InvalidDataException whenever the client receives a status other than 200 OK with valid JSON/GraphQL results
     */
    public function request(string $query, ?stdClass $variables = null): Response;
}
