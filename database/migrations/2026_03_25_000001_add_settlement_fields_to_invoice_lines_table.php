<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->enum('status', ['active', 'void'])->default('active')->after('description_snapshot');
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['invoice_id', 'status']);
            $table->dropColumn(['status', 'voided_at', 'void_reason']);
        });
    }
};
