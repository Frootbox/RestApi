<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\RestApi\Response;

interface ResponseInterface
{
    /**
     * Convert payload to json
     * 
     * @return string
     */
    public function toJson(): string;
}
