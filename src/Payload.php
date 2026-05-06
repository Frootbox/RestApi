<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
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

    /**
     * @param string $parameter
     * @return int|string|array|null
     */
    public function getBodyParameter(string $parameter): int|float|string|array|bool|null
    {
        return $this->bodyParameters[$parameter] ?? null;
    }

    /**
     * @param string $parameter
     * @return int|float|string|bool|null
     */
    public function getQueryParameter(string $parameter): int|float|string|bool|null
    {
        if (!isset($this->queryParameters[$parameter])) {
            return null;
        }

        $value = $this->queryParameters[$parameter];

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float)$value;
            }
            return (int)$value;
        }

        return $value;
    }

    /**
     * @param string $parameter
     * @return bool
     */
    public function hasBodyParameter(string $parameter): bool
    {
        return isset($this->bodyParameters[$parameter]);
    }

    /**
     * @param string $parameter
     * @return bool
     */
    public function hasQueryParameter(string $parameter): bool
    {
        return isset($this->queryParameters[$parameter]);
    }
}
