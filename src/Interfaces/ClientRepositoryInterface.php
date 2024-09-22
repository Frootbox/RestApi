<?php
/**
 *
 */

namespace Frootbox\RestApi\Interfaces;

interface ClientRepositoryInterface
{
    /**
     * @param string $clientId
     * @param string $clientSecret
     * @return mixed
     */
    public function validate(string $clientId, string $clientSecret): void;
}
