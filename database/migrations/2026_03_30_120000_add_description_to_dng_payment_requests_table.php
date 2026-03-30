<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('fee_type');
        });
    }

    public function down(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
