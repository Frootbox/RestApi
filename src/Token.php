<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\RestApi;

class Token
{
    /**
     * @param array $payload
     */
    public function __construct(
        protected array $payload = [],
    )
    { }

    public function getPayload(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }

    public function getPayloads(): array
    {
        return $this->payload;
    }
}
