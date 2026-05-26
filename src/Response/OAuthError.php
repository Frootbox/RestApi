<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

declare(strict_types=1);

namespace Frootbox\RestApi\Response;

class OAuthError extends Payload
{
    public function __construct(
        string $error,
        ?string $errorDescription = null,
        int $statusCode = 400,
        ?string $errorUri = null,
    )
    {
        $payload = [
            'error' => $error,
        ];

        if ($errorDescription !== null) {
            $payload['error_description'] = $errorDescription;
        }

        if ($errorUri !== null) {
            $payload['error_uri'] = $errorUri;
        }

        parent::__construct(
            payload: $payload,
            statusCode: $statusCode,
            headers: [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
