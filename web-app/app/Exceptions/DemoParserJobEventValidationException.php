<?php

namespace App\Exceptions;

use Exception;

class DemoParserJobEventValidationException extends Exception
{
    public function __construct(string $message = 'Event validation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
