<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use Spawnia\Sailor\Client;
use Spawnia\Sailor\Configuration;
use Spawnia\Sailor\EndpointConfig;

class GenericSailorEndpointConfig extends EndpointConfig
{
    private SailorClientInterface $client;
    private array $endpointParams;

    public function __construct(
        SailorClientInterface $client,
        array $endpoints,
        string $configPath,
        string $endpointName
    ) {
        $this->client = $client;
        $this->endpointParams = $endpoints[$endpointName];
        Configuration::setEndpoint($configPath, $endpointName, $this);
    }

    /**
     * {@inheritDoc}
     */
    public function makeClient(): Client
    {
        return $this->client
            ->withUrl($this->endpointParams['url'])
            ->withPost($this->endpointParams['post']);
    }

    /**
     * {@inheritDoc}
     */
    public function namespace(): string
    {
        return $this->endpointParams['namespace'];
    }

    /**
     * {@inheritDoc}
     */
    public function targetPath(): string
    {
        return $this->endpointParams['generation_path'];
    }

    /**
     * {@inheritDoc}
     */
    public function searchPath(): string
    {
        return $this->endpointParams['operations_path'];
    }

    /**
     * {@inheritDoc}
     */
    public function schemaPath(): string
    {
        return $this->endpointParams['schema_path'];
    }
}
