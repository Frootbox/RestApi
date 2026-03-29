<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\RestApi\Exception;

class InvalidInput extends AbstractException
{
    protected int $httpStatusCode = 400;
    protected $message = "Invalid input";
    
}
