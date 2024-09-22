<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
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
