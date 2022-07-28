<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\Response;
use stdClass;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use function is_array;
use function is_object;
use function is_string;
use function urlencode;

if (!interface_exists(RequestFactoryInterface::class)) {
    throw new LogicException('You cannot use the SailorPssr18Client as the "psr/http-factory" package is not installed.');
}

if (!interface_exists(ClientInterface::class)) {
    throw new LogicException('You cannot use the SailorPssr18Client as the "psr/http-client" package is not installed.');
}

class SailorPsr18Client extends AbstractSailorClient
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $requestFactory ??= class_exists(Psr17Factory::class)
            ? new Psr17Factory() : null;
        $requestFactory ??= class_exists(HttpFactory::class)
            ? new HttpFactory() : null;
        $requestFactory ??= class_exists(Psr17FactoryDiscovery::class)
            ? Psr17FactoryDiscovery::findRequestFactory() : null;

        $streamFactory ??= $requestFactory instanceof StreamFactoryInterface ? $requestFactory : null;
        $streamFactory ??= class_exists(Psr17FactoryDiscovery::class)
            ? Psr17FactoryDiscovery::findStreamFactory() : null;

        $responseFactory = $requestFactory instanceof ResponseFactoryInterface ? $requestFactory : null;
        $responseFactory ??= class_exists(Psr17FactoryDiscovery::class)
            ? Psr17FactoryDiscovery::findResponseFactory() : null;

        if (null === $requestFactory || null === $streamFactory) {
            throw new LogicException("SailorPsr18Client requires a psr/http-factory-implementation to be present (at least one of 'nyholm/psr7' or 'guzzlehttp/psr7' - others may be supported coupled with 'php-http/discovery')");
        }
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;

        $client ??= class_exists(Psr18Client::class)
                ? new Psr18Client(HttpClient::create(), $responseFactory, $streamFactory) : null;
        $client ??= $this->client ?? class_exists(GuzzleClient::class)
                ? new GuzzleClient() : null;
        $client ??= $this->client ?? class_exists(Psr18ClientDiscovery::class)
                ? Psr18ClientDiscovery::find() : null;
        if (null === $client) {
            throw new LogicException("SailorPsr18Client requires a psr/http-client-implementation to be present (at least one of 'symfony/http-client', 'guzzlehttp/http', 'php-http/socket-client' or 'php-http/curl-client' - others may be supported coupled with 'php-http/discovery'");
        }
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidDataException      whenever the client receives a status other than 200 OK with valid JSON/GraphQL results
     * @throws RequestExceptionInterface Whenever a request fails for "user" reasons
     * @throws NetworkExceptionInterface Whenever a request fails for external reasons
     * @throws ClientExceptionInterface  Whenever the client fails for both previous (or other) reasons
     */
    public function request(string $query, stdClass $variables = null): Response
    {
        $response = $this->client->sendRequest(
            $this->composeRequest($query, $variables)
        );

        return Response::fromResponseInterface($response);
    }

    protected function composeRequest(string $query, ?stdClass $variables = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest($this->post ? 'POST' : 'GET', $this->url);
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $queryParams = $this->getQueryParamsString($this->queryParams);

        if ($this->post) {
            $body = ['query' => $query];
            if (null !== $variables) {
                $body['variables'] = $variables;
            }
            $bodyStream = $this->streamFactory->createStream(
                $this->serializer->serialize($body, 'json', $this->serializationContext)
            );

            return $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($bodyStream)
                ->withUri($request->getUri()->withQuery($queryParams));
        }

        $getQuery = urlencode($this->serializer->serialize($query, 'json', $this->serializationContext));
        $getVariables = '';
        if (null !== $variables) {
            $getVariables = '&variables='.urlencode(
                $this->serializer->serialize($variables, 'json', $this->serializationContext)
            );
        }

        return $request->withUri(
            $request->getUri()
                ->withQuery("query={$getQuery}$getVariables$queryParams")
        );
    }

    protected function getQueryParamsString(array $params, string $arrayKey = ''): string
    {
        $queryParams = '';
        foreach ($params as $name => $param) {
            if (!empty($arrayKey)) {
                if (is_string($name)) {
                    $name = "{$arrayKey}[$name]";
                } else {
                    $name = "{$arrayKey}[]";
                }
            }
            if (is_array($param)) {
                $queryParams .= '&'.$this->getQueryParamsString($param, $name);
                continue;
            }
            if (is_object($param)) {
                $param = $this->serializer->serialize($param, 'json', $this->serializationContext);
            }
            $queryParams .= "&$name=$param";
        }

        return trim($queryParams, '&');
    }
}
