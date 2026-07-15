<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table): void {
            $table->json('review_evidence')->nullable()->after('push_response');
        });
    }

    public function down(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table): void {
            $table->dropColumn('review_evidence');
        });
    }
};
