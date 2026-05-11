<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gpa_calculations', function (Blueprint $table) {
            $table->dropForeign(['finalized_by_id']);
            $table->foreign('finalized_by_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gpa_calculations', function (Blueprint $table) {
            $table->dropForeign(['finalized_by_id']);
            $table->foreign('finalized_by_id')
                ->references('id')->on('lectures')
                ->nullOnDelete();
        });
    }
};
