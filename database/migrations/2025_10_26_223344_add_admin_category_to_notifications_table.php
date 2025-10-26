<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'admin' to the enum values for category column
        DB::statement("ALTER TABLE notifications MODIFY COLUMN category ENUM('academic', 'system', 'finance', 'personal', 'event', 'club', 'administrative', 'admin') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'admin' from the enum values
        DB::statement("ALTER TABLE notifications MODIFY COLUMN category ENUM('academic', 'system', 'finance', 'personal', 'event', 'club', 'administrative') NOT NULL");
    }
};
