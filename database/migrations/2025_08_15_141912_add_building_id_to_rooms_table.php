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
        // Step 1: Add building_id column as nullable first
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('building_id')->nullable()->after('campus_id');
        });
        
        // Step 2: Migrate data from building string to building_id
        $this->migrateExistingBuildingData();
        
        // Step 3: Make building_id required and add foreign key constraint
        Schema::table('rooms', function (Blueprint $table) {
            // Make building_id NOT NULL and add foreign key
            $table->unsignedBigInteger('building_id')->nullable(false)->change();
            $table->foreign('building_id')->references('id')->on('buildings')->restrictOnDelete();
            
            // Drop the old string-based building column
            $table->dropColumn('building');
            
            // Update index to use building_id instead of building string
            $table->dropIndex(['building', 'floor']);
            $table->index(['building_id', 'floor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Recreate the old string-based building column
            $table->string('building', 50)->nullable()->after('code');
        });
        
        // Migrate data back from building_id to building string
        $this->restoreExistingBuildingData();
        
        Schema::table('rooms', function (Blueprint $table) {
            // Restore original index
            $table->dropIndex(['building_id', 'floor']);
            $table->index(['building', 'floor']);
            
            // Drop the foreign key and column
            $table->dropForeign(['building_id']);
            $table->dropColumn('building_id');
        });
    }
    
    /**
     * Migrate existing building string data to building_id foreign key
     */
    private function migrateExistingBuildingData(): void
    {
        // Get all rooms with building strings
        $rooms = \DB::table('rooms')->whereNotNull('building')->get();
        
        foreach ($rooms as $room) {
            // Try to find matching building by name
            $building = \DB::table('buildings')
                ->where('name', $room->building)
                ->orWhere('code', $room->building)
                ->first();
                
            if ($building) {
                \DB::table('rooms')
                    ->where('id', $room->id)
                    ->update(['building_id' => $building->id]);
            } else {
                // Create a default building for this campus if no match found
                $campus = \DB::table('campuses')->find($room->campus_id);
                if ($campus) {
                    $buildingId = \DB::table('buildings')->insertGetId([
                        'campus_id' => $room->campus_id,
                        'name' => $room->building,
                        'code' => \Str::slug($room->building),
                        'description' => 'Auto-created during migration',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    \DB::table('rooms')
                        ->where('id', $room->id)
                        ->update(['building_id' => $buildingId]);
                }
            }
        }
    }
    
    /**
     * Restore building string data from building_id for rollback
     */
    private function restoreExistingBuildingData(): void
    {
        $rooms = \DB::table('rooms')
            ->join('buildings', 'rooms.building_id', '=', 'buildings.id')
            ->select('rooms.id', 'buildings.name as building_name')
            ->get();
            
        foreach ($rooms as $room) {
            \DB::table('rooms')
                ->where('id', $room->id)
                ->update(['building' => $room->building_name]);
        }
    }
};
