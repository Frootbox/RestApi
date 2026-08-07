<?php

declare(strict_types=1);

namespace Frootbox\RestApi\Interface;

interface PublicClientRepositoryInterface
{
    public function validatePublicClient(string $clientId, ?callable $onValidateClient = null): void;
}
