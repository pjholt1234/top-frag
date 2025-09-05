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
        Schema::table('player_round_events', function (Blueprint $table) {
            // Remove the old flash_duration column
            $table->dropColumn('flash_duration');

            // Add the new flashes_thrown column
            $table->integer('flashes_thrown')->default(0)->after('damage_dealt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_round_events', function (Blueprint $table) {
            // Remove the flashes_thrown column
            $table->dropColumn('flashes_thrown');

            // Add back the flash_duration column
            $table->decimal('flash_duration', 8, 3)->default(0)->after('damage_dealt');
        });
    }
};
