<?php

namespace App\Exceptions;

use Exception;

class PlayerNotFound extends Exception
{
    public function __construct(string $message = 'Player not found', array $context = [])
    {
        parent::__construct($message, 0, null);
        $this->context = $context;
    }

    public array $context;
}
