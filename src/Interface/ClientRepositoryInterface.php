<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\RestApi\Interface;

interface ClientRepositoryInterface
{
    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param callable|null $onValidateClient
     * @return void
     */
    public function validate(string $clientId, string $clientSecret, callable $onValidateClient = null): void;

    /**
     * @param string $apiKey
     * @param callable|null $onValidateClient
     * @return void
     */
    public function validateApiKey(string $apiKey, callable $onValidateClient = null): void;
}
