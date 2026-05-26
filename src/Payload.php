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
    protected ?string $contentType = null;
    protected string $rawBody = '';

    public function __construct(
        ?array $queryParameters = null,
        ?array $bodyParameters = null,
        ?string $rawBody = null,
        ?string $contentType = null,
    )
    {
        $this->queryParameters = $queryParameters ?? $_GET;
        $this->contentType = $contentType ?? ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null);

        if ($bodyParameters !== null) {
            $this->bodyParameters = $bodyParameters;
            $this->rawBody = $rawBody ?? '';

            return;
        }

        // Parse request body
        $this->rawBody = $rawBody ?? (string) file_get_contents('php://input');
        $requestBody = trim($this->rawBody);

        if ($requestBody === '') {
            if (!empty($_POST)) {
                $this->bodyParameters = $_POST;
            }

            return;
        }

        if ($this->isJsonRequest()) {

            // Validate json
            if (!\json_validate($requestBody)) {
                throw new \Frootbox\RestApi\Exception\InvalidInput("Body payload contains invalid JSON.");
            }

            // Parse request body
            $requestBody = json_decode($requestBody, true);

            if (!empty($requestBody)) {
                $this->bodyParameters = $requestBody;
            }

            return;
        }

        if ($this->isFormUrlencodedRequest()) {
            parse_str($requestBody, $bodyParameters);
            $this->bodyParameters = $bodyParameters;

            return;
        }

        if (\json_validate($requestBody)) {
            $this->bodyParameters = json_decode($requestBody, true) ?: [];

            return;
        }

        throw new \Frootbox\RestApi\Exception\InvalidInput("Unsupported request content type.");
    }

    /**
     * @param string $parameter
     * @return int|string|array|null
     */
    public function getBodyParameter(string $parameter): int|float|string|array|bool|null
    {
        return $this->bodyParameters[$parameter] ?? null;
    }

    public function getBodyParameters(): array
    {
        return $this->bodyParameters;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
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

    public function getQueryParameters(): array
    {
        return $this->queryParameters;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
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

    protected function isFormUrlencodedRequest(): bool
    {
        return str_starts_with(strtolower((string) $this->contentType), 'application/x-www-form-urlencoded');
    }

    protected function isJsonRequest(): bool
    {
        $contentType = strtolower((string) $this->contentType);
        $mediaType = strtok($contentType, ';') ?: '';

        return $contentType === ''
            || str_starts_with($contentType, 'application/json')
            || str_ends_with($mediaType, '+json');
    }
}
