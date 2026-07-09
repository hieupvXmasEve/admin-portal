<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_charges', function (Blueprint $table): void {
            if (Schema::hasColumn('finance_charges', 'active_source_key')) {
                $table->dropUnique('finance_charges_active_source_key_unique');
                $table->dropColumn('active_source_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_charges', function (Blueprint $table): void {
            if (! Schema::hasColumn('finance_charges', 'active_source_key')) {
                $table->string('active_source_key', 255)->nullable()->after('source_id');
                $table->unique('active_source_key', 'finance_charges_active_source_key_unique');
            }
        });
    }
};
