<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

declare(strict_types = 1);

namespace Frootbox\RestApi\Response;

class Payload implements ResponseInterface
{
    /**
     * @param array $payload
     */
    public function __construct(
        protected array $payload = [],
        protected int $statusCode = 200,
        protected array $headers = [],
    )
    { }

    public function addHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param array $payload
     * @return void
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->payload);
    }

    /**
     * Construct payload from array
     *
     * @param array $payload
     * @return static
     */
    public static function fromArray(array $payload): static
    {
        return new self(
            payload: $payload,
        );
    }
}
