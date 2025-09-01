<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class UsageLimitExceededException extends Exception
{
    public function __construct(string $message = 'Usage limit exceeded', int $code = 429, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
