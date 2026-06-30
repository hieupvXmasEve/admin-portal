<?php

declare(strict_types=1);

use App\Shared\Support\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(sprintf(
            "ALTER TABLE users MODIFY COLUMN type ENUM(%s) NOT NULL DEFAULT '%s'",
            $this->quotedUserTypes(UserType::values()),
            UserType::STAFF->value,
        ));
    }

    public function down(): void
    {
        DB::statement(sprintf(
            "ALTER TABLE users MODIFY COLUMN type ENUM(%s) NOT NULL DEFAULT '%s'",
            $this->quotedUserTypes([
                UserType::STAFF->value,
                UserType::STUDENT->value,
                UserType::LECTURER->value,
                UserType::PARENT->value,
            ]),
            UserType::STAFF->value,
        ));
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedUserTypes(array $values): string
    {
        return collect($values)
            ->map(fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(',');
    }
};
