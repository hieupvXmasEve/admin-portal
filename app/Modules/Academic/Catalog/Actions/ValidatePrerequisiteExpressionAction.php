<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Services\PrerequisiteLogicService;

class ValidatePrerequisiteExpressionAction
{
    public function __construct(private readonly PrerequisiteLogicService $prerequisiteLogic) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function handle(string $expression): array
    {
        return $this->prerequisiteLogic->parseAndStorePrerequisiteExpression(
            unitId: 0,
            expression: $expression,
            validationOnly: true,
        );
    }
}
