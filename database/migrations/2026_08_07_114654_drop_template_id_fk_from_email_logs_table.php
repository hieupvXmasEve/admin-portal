<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropIndex(['template_id', 'status']);
            $table->dropColumn('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('subject')->constrained('email_templates')->onDelete('set null');
            $table->index(['template_id', 'status']);
        });
    }
};
