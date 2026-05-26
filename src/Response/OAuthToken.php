<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

declare(strict_types=1);

namespace Frootbox\RestApi\Response;

class OAuthToken extends Payload
{
    public function __construct(array $payload)
    {
        parent::__construct(
            payload: $payload,
            headers: [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
