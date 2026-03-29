<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\RestApi\Exception;

class NotAuthed extends AbstractException
{
    protected int $httpStatusCode = 401;
    protected $message = "Not authed";
}
