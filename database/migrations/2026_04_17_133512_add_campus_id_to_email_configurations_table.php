<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_configurations', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('id')
                ->constrained('campuses')->onDelete('cascade');
            $table->index('campus_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_configurations', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropIndex(['campus_id']);
            $table->dropColumn('campus_id');
        });
    }
};
