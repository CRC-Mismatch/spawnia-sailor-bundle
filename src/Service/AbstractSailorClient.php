<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Service;

use Mismatch\SpawniaSailorBundle\OperationVisitor;
use Spawnia\Sailor\Error\InvalidDataException;
use Spawnia\Sailor\ObjectLike;
use Spawnia\Sailor\Operation;
use Spawnia\Sailor\Result;
use stdClass;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;
use function get_class;

abstract class AbstractSailorClient implements SailorClientInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    protected string $url = '';
    protected bool $post = true;

    /** @var array<string, string> */
    protected array $headers = [];

    /** @var array<string, mixed> */
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
}
