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
        Schema::table('students', function (Blueprint $table) {
            $table->string('emergency_contact_email')->nullable()->after('emergency_contact_relationship');
            $table->string('emergency_contact_name_1')->nullable()->after('emergency_contact_email');
            $table->string('emergency_contact_email_1')->nullable()->after('emergency_contact_name_1');
            $table->string('emergency_contact_phone_1')->nullable()->after('emergency_contact_email_1');
            $table->string('emergency_contact_relationship_1')->nullable()->after('emergency_contact_phone_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_email',
                'emergency_contact_name_1',
                'emergency_contact_email_1',
                'emergency_contact_phone_1',
                'emergency_contact_relationship_1'
            ]);
        });
    }
};
