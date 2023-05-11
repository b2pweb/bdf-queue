<?php

namespace Bdf\Queue\Connection\Exception;

/**
 * Exception for errors on the server side
 * This exception should not be caught by the consumer
 */
class ServerException extends ConnectionException
{
}
