<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use Mismatch\SpawniaSailorBundle\OperationVisitor;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Spawnia\Sailor\Client;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\ObjectLike;
use Spawnia\Sailor\Operation;
use Spawnia\Sailor\Response;
use Spawnia\Sailor\Result;
use stdClass;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;
use function get_class;
use function urlencode;

class SailorPsr18Client implements Client, SerializerAwareInterface
{
    use SerializerAwareTrait;

    private ?ClientInterface $client;
    private ?RequestFactoryInterface $requestFactory;
    private ?StreamFactoryInterface $streamFactory;
    private ?UriFactoryInterface $uriFactory;
    private string $url = '';
    private bool $post = true;
    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $serializationContext = [];

    public function __construct(
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UriFactoryInterface $uriFactory = null
    ) {
        $this->client = $client ?? new Psr18Client(HttpClient::create(), new Psr17Factory(), $streamFactory);
        $this->requestFactory = $requestFactory ?? new Psr17Factory();
        $this->streamFactory = $streamFactory ?? new Psr17Factory();
        $this->uriFactory = $uriFactory ?? new Psr17Factory();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSerializationContext(): array
    {
        return $this->serializationContext;
    }

    /**
     * @param array<string, mixed> $serializationContext
     */
    public function setSerializationContext(array $serializationContext): self
    {
        $new = clone $this;
        $new->serializationContext = $serializationContext;

        return $new;
    }

    /**
     * @template TResult of \Spawnia\Sailor\Result
     *
     * @param Operation<TResult> $operation
     * @param array              $args
     *
     * @throws InvalidDataException      whenever the client receives a status other than 200 OK and valid JSON/GraphQL results
     * @throws RequestExceptionInterface Whenever a request fails for "user" reasons
     * @throws NetworkExceptionInterface Whenever a request fails for external reasons
     * @throws ClientExceptionInterface  Whenever the client fails for both previous (or other) reasons
     *
     * @return TResult
     */
    public function execute(Operation $operation, ...$args): Result
    {
        $visitor = new OperationVisitor($operation);
        $variables = new stdClass();
        $arguments = $visitor->converters();
        foreach ($args as $index => $arg) {
            if (ObjectLike::UNDEFINED === $arg) {
                continue;
            }

            [$name, $typeConverter] = $arguments[$index];
            $variables->{$name} = $typeConverter->toGraphQL($arg);
        }

        $response = $this->request($operation::document(), $variables);

        $child = get_class($operation);
        $parts = explode('\\', $child);
        $basename = end($parts);

        /** @var class-string<TResult> $resultClass */
        $resultClass = $child.'\\'.$basename.'Result';

        return $resultClass::fromResponse($response);
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
                ->withBody($bodyStream);
        }

        $getQuery = urlencode($this->serializer->serialize($query, 'json', $this->serializationContext));
        $getVariables = '';
        if (null !== $variables) {
            $getVariables = '&variables='.urlencode(
                $this->serializer->serialize($variables, 'json', $this->serializationContext)
            );
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

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $new = clone $this;
        $new->headers = $headers;

        return $new;
    }

    public function addHeader(string $name, string $value): self
    {
        $new = clone $this;
        $new->headers[$name] = $value;

        return $new;
    }

    public function removeHeader(string $name): self
    {
        $new = clone $this;
        unset($new->headers[$name]);

        return $new;
    }
}
