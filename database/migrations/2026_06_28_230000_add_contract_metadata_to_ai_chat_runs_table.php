<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->string('prompt_version', 100)->nullable()->after('model');
            $table->string('catalog_version', 100)->nullable()->after('prompt_version');
            $table->string('tool_schema_version', 100)->nullable()->after('catalog_version');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'prompt_version',
                'catalog_version',
                'tool_schema_version',
            ]);
        });
    }
};
