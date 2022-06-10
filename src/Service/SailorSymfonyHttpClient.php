<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use Closure;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\Response;
use stdClass;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function is_array;
use function is_object;

class SailorSymfonyHttpClient extends AbstractSailorClient
{
    private ?HttpClientInterface $client;

    public function __construct(
        ?HttpClientInterface $client = null
    ) {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidDataException          Whenever the client receives a status other than 200 OK with valid JSON/GraphQL results
     * @throws TransportExceptionInterface   If any transport-level errors occur
     * @throws RedirectionExceptionInterface Received a 3xx status and the "max_redirects" option has been reached
     * @throws ClientExceptionInterface      Whenever a request fails for "user" reasons
     * @throws ServerExceptionInterface      Whenever a request fails for external server reasons
     */
    public function request(string $query, stdClass $variables = null): Response
    {
        $response = $this->client->request(
            $this->post ? 'POST' : 'GET',
            $this->url,
            $this->composeRequestOptions($query, $variables)
        );

        $json = $response->getContent();
        if (200 !== $response->getStatusCode()) {
            throw new InvalidDataException("Response must have status code 200, got: {$response->getStatusCode()}");
        }
        try {
            $responseObject = $this->serializer->deserialize(
                $json,
                stdClass::class,
                'json'
            );
        } catch (NotEncodableValueException $jsonException) {
            throw new InvalidDataException("Received a response that is invalid JSON: {$json}", 0, $jsonException);
        }

        if (!$responseObject instanceof stdClass) {
            throw new InvalidDataException("A response to a GraphQL operation must be a map, got: {$json}");
        }

        return Response::fromStdClass($responseObject);
    }

    protected function composeRequestOptions(string $query, ?stdClass $variables = null): array
    {
        $requestOptions = [
            'headers' => $this->headers,
            'query' => $this->queryParams,
        ];

        if ($this->post) {
            $body = ['query' => $query];
            if (null !== $variables) {
                $body['variables'] = $variables;
            }
            $requestOptions['body'] = $this->serializer->serialize($body, 'json', $this->serializationContext);
            $requestOptions['headers']['Content-Type'] = 'application/json';
        } else {
            $requestOptions['query']['query'] = $query;
            if (null !== $variables) {
                $requestOptions['query']['variables'] = $variables;
            }
        }

        $requestOptions['query'] = array_map(
            Closure::fromCallable([self::class, 'encodeQueryParam'])->bindTo($this),
            $requestOptions['query']
        );

        return $requestOptions;
    }

    /**
     * @param string|array|object $input
     *
     * @return string|array
     */
    protected function encodeQueryParam($input)
    {
        if (is_array($input)) {
            return array_map(Closure::fromCallable([self::class, 'encodeQueryParam'])->bindTo($this), $input);
        }
        if (is_object($input)) {
            $input = $this->serializer->serialize($input, 'json', $this->serializationContext);
        }

        return urlencode($input);
    }
}
