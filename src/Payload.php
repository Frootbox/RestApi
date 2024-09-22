<?php
/**
 *
 */

declare(strict_types=1);

namespace Frootbox\RestApi;

class Payload
{
    protected array $queryParameters = [];
    protected array $bodyParameters = [];

    public function __construct()
    {
        $this->queryParameters = $_GET;
        $this->bodyParameters = $_POST;
    }

    public function getBodyParameter(string $parameter): ?string
    {
        return $this->bodyParameters[$parameter] ?? null;
    }

    public function getQueryParameter(string $parameter): ?string
    {
        return $this->queryParameters[$parameter] ?? null;
    }
}
