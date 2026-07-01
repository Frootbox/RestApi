<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\RestApi\Exception;

class Forbidden extends AbstractException
{
    protected int $httpStatusCode = 403;
    protected $message = "Forbidden";
}
