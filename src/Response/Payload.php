<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
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
    )
    { }

    /**
     * @param array $payload
     * @return void
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
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
