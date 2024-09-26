<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\RestApi\Interface;

interface ClientRepositoryInterface
{
    /**
     * @param string $clientId
     * @param string $clientSecret
     * @return mixed
     */
    public function validate(string $clientId, string $clientSecret): void;
}
