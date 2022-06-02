<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Services;

use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Spawnia\Sailor\Client;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\Response;
use stdClass;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use function urlencode;
use const JSON_THROW_ON_ERROR;

class SailorPsr18Client implements Client
{
    private ?ClientInterface $client;
    private ?RequestFactoryInterface $requestFactory;
    private ?StreamFactoryInterface $streamFactory;
    private ?UriFactoryInterface $uriFactory;
    private string $url = '';
    private bool $post = true;

    public function __construct(?ClientInterface $client = null, ?RequestFactoryInterface $requestFactory = null, ?StreamFactoryInterface $streamFactory = null, ?UriFactoryInterface $uriFactory = null)
    {
        $this->client = $client ?? new Psr18Client(HttpClient::create(), new Psr17Factory(), $streamFactory);
        $this->requestFactory = $requestFactory ?? new Psr17Factory();
        $this->streamFactory = $streamFactory ?? new Psr17Factory();
        $this->uriFactory = $uriFactory ?? new Psr17Factory();
    }

    /**
     * {@inheritDoc}
     *
     * @throws ClientExceptionInterface|InvalidDataException|JsonException
     */
    public function request(string $query, stdClass $variables = null): Response
    {
        $response = $this->client->sendRequest(
            $this->composeRequest($query, $variables)
        );

        return Response::fromResponseInterface($response);
    }

    /**
     * @throws JsonException
     */
    protected function composeRequest(string $query, ?stdClass $variables = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest($this->post ? 'POST' : 'GET', $this->url);

        if ($this->post) {
            $body = ['query' => $query];
            if (null !== $variables) {
                $body['variables'] = $variables;
            }
            $bodyStream = $this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR));

            return $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($bodyStream);
        }

        $getQuery = urlencode(json_encode($query, JSON_THROW_ON_ERROR));
        $getVariables = '';
        if (null !== $variables) {
            $getVariables = '&variables='.urlencode(json_encode($variables, JSON_THROW_ON_ERROR));
        }

        return $request->withUri($this->uriFactory->createUri("{$this->url}?query={$getQuery}$getVariables"));
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $new = clone $this;
        $new->url = $url;

        return $new;
    }

    public function isPost(): bool
    {
        return $this->post;
    }

    public function setPost(bool $post): self
    {
        $new = clone $this;
        $new->post = $post;

        return $new;
    }
}
