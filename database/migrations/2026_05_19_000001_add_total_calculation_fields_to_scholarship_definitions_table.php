<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_definitions', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->nullable()->after('amount');
            $table->unsignedInteger('total_terms')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_definitions', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'total_terms']);
        });
    }
};
