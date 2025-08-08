<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class BusinessLogicException extends Exception
{
    protected array $errors;

    public function __construct(string $message, array $errors = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }
}
