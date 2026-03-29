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

    /**
     * @param string $key
     * @return string|null
     */
    public function getPayload(string $key): ?string
    {
        return $this->payload[$key] ?? null;
    }
}
