<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use Mismatch\SpawniaSailorBundle\OperationVisitor;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\ObjectLike;
use Spawnia\Sailor\Operation;
use Spawnia\Sailor\Result;
use stdClass;
use Symfony\Component\Serializer\SerializerAwareTrait;
use function get_class;

abstract class AbstractSailorClient implements SailorClientInterface
{
    use SerializerAwareTrait;

    protected string $url = '';
    protected bool $post = true;

    /** @var array<string, string> */
    protected array $headers = [];

    /** @var array<string, array|string|object> */
    protected array $queryParams = [];

    /** @var array<string, mixed> */
    protected array $serializationContext = [];

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
    public function execute(Operation $operation, ...$args): Result
    {
        $visitor = new OperationVisitor($operation);
        $variables = new stdClass();
        $arguments = $visitor->converters();
        $arguments += array_column($arguments, null, 0);
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
     * @return array<string, mixed>
     */
    public function getSerializationContext(): array
    {
        return $this->serializationContext;
    }

    /**
     * @param array<string, mixed> $serializationContext
     */
    public function withSerializationContext(array $serializationContext): self
    {
        $new = clone $this;
        $new->serializationContext = $serializationContext;

        return $new;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function withUrl(string $url): self
    {
        $new = clone $this;
        $new->url = $url;

        return $new;
    }

    public function isPost(): bool
    {
        return $this->post;
    }

    public function withPost(bool $post): self
    {
        $new = clone $this;
        $new->post = $post;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function withHeaders(array $headers): self
    {
        $new = clone $this;
        $new->headers = $headers;

        return $new;
    }

    public function withHeader(string $name, string $value): self
    {
        $new = clone $this;
        $new->headers[$name] = $value;

        return $new;
    }

    public function withoutHeader(string $name): self
    {
        $new = clone $this;
        unset($new->headers[$name]);

        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $queryParams): self
    {
        $new = clone $this;
        $new->queryParams = $queryParams;

        return $new;
    }

    public function withQueryParam(string $key, $value): self
    {
        $new = clone $this;
        $new->queryParams[$key] = $value;

        return $new;
    }

    public function withoutQueryParam(string $key): self
    {
        $new = clone $this;
        unset($new->queryParams[$key]);

        return $new;
    }
}
