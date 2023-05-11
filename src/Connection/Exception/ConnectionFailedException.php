<?php

namespace Bdf\Queue\Connection\Exception;

/**
 * Exception thrown when the connection cannot be established
 * Unlike {@see ConnectionLostException}, this exception can be caused by a misconfiguration
 */
class ConnectionFailedException extends ConnectionException
{

}
