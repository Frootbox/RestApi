<?php
/**
 * @author Ja
 */

namespace Frootbox\RestApi;

class Token
{
    /**
     * @param $payload
     */
    public function __construct(
        protected $payload = [],
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
