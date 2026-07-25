<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class InvalidProgressionState extends ValidationException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        $validator = Validator::make([], []);
        $validator->errors()->add($field, $message);

        parent::__construct($validator);
    }
}
