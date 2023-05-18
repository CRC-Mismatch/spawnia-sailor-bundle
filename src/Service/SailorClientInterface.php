<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use Spawnia\Sailor\Client;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\Operation;
use Spawnia\Sailor\Response;
use Spawnia\Sailor\Result;
use stdClass;
use Symfony\Component\Serializer\SerializerAwareInterface;

interface SailorClientInterface extends Client, SerializerAwareInterface
{
    /**
     * @template TResult of Result
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

    /**
     * @return array<string, array|string|object>
     */
    public function getQueryParams(): array;

    /**
     * @param array<string, array|bool|float|int|string|object> $queryParams
     *
     * @return static
     */
    public function withQueryParams(array $queryParams): self;

    /**
     * @param array|bool|float|int|string|object $value
     *
     * @return static
     */
    public function withQueryParam(string $key, $value): self;

    /**
     * @return static
     */
    public function withoutQueryParam(string $key): self;

    public function getUrl(): string;

    /**
     * @return static
     */
    public function withUrl(string $url): self;

    public function isPost(): bool;

    /**
     * @return static
     */
    public function withPost(bool $post): self;

    /**
     * @return array<string, array<int, string|null>|string|null>
     */
    public function getHeaders(): array;

    /**
     * @param array<int, string|null>|string|null $value
     *
     * @return static
     */
    public function withHeader(string $name, $value): self;

    /**
     * @param array<string, array<int, string|null>|string|null> $headers
     *
     * @return static
     */
    public function withHeaders(array $headers): self;

    /**
     * @return static
     */
    public function withoutHeader(string $name): self;

    /**
     * @return array<string, mixed>
     */
    public function getSerializationContext(): array;

    /**
     * @param array<string, mixed> $serializationContext
     *
     * @return static
     */
    public function withSerializationContext(array $serializationContext): self;
}
