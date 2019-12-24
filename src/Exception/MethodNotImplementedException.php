<?php

namespace Bdf\Queue\Exception;

/**
 * MethodNotImplementedException
 */
class MethodNotImplementedException extends \RuntimeException
{
    /**
     * MethodNotImplementedException constructor.
     *
     * @param string $methodName  The name of the method
     */
    public function __construct($methodName)
    {
        parent::__construct(sprintf('The %s() is not implemented.', $methodName));
    }
}
