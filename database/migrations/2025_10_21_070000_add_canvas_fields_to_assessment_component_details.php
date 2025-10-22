<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_component_details', function (Blueprint $table) {
            $table->string('canvas_assignment_id', 50)->nullable()->after('name');
            $table->timestamp('canvas_synced_at')->nullable()->after('canvas_assignment_id');
            
            $table->index('canvas_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_component_details', function (Blueprint $table) {
            $table->dropIndex(['canvas_assignment_id']);
            $table->dropColumn(['canvas_assignment_id', 'canvas_synced_at']);
        });
    }
};
