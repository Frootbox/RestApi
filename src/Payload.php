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

        // Parse request body
        $requestBody = trim(file_get_contents('php://input'));

        if (!empty($requestBody)) {

            // Validate json
            if (!\json_validate($requestBody)) {
                throw new \Frootbox\RestApi\Exception\InvalidInput("Body payload contains invalid JSON.");
            }

            // Parse request body
            $requestBody = json_decode($requestBody, true);

            if (!empty($requestBody)) {
                $this->bodyParameters = $requestBody;
            }
        }
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
