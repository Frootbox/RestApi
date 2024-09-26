<?php
/**
 *
 */

namespace Frootbox\RestApi\Exception;

abstract class AbstractException extends \Exception
{
    protected int $httpStatusCode = 500;

    /**
     * @return int
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

}
