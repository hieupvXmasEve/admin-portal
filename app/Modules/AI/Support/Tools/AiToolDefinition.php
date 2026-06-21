<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

class AiToolDefinition
{
    /**
     * @param  list<string>  $safeErrorCodes
     */
    public function __construct(
        private readonly string $name,
        private readonly string $schemaVersion,
        private readonly string $permission,
        private readonly string $description,
        private readonly array $safeErrorCodes,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function schemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function permission(): string
    {
        return $this->permission;
    }

    /**
     * @return array{name: string, schema_version: string, permission: string, description: string, safe_error_codes: list<string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'schema_version' => $this->schemaVersion,
            'permission' => $this->permission,
            'description' => $this->description,
            'safe_error_codes' => $this->safeErrorCodes,
        ];
    }
}
