<?php

namespace App\Exceptions\Api;

use Exception;

class ValidationApiException extends Exception
{
    /** @param array<string, array<int, string>> $errors */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
